<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestBody;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class BufferingParser {
    const DEFAULT_FIELD_LENGTH_LIMIT = 16384;
    const DEFAULT_FIELD_COUNT_LIMIT = 200;

    /** @var Promise|null */
    private $parsePromise;

    /** @var RequestBody */
    private $body;

    /** @var string|null */
    private $boundary;

    /** @var int Prevent buffering of arbitrary long names and fail instead */
    private $fieldLengthLimit;

    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private $fieldCountLimit;

    /**
     * @param Request $request
     * @param int     $fieldLengthLimit Maximum length of each individual field in bytes.
     * @param int     $fieldCountLimit Maximum number of fields that the body may contain.
     */
    public function __construct(
        Request $request,
        int $fieldLengthLimit = self::DEFAULT_FIELD_LENGTH_LIMIT,
        int $fieldCountLimit = self::DEFAULT_FIELD_COUNT_LIMIT
    ) {
        $type = $request->getHeader("content-type");
        $this->body = $request->getBody();
        $this->fieldLengthLimit = $fieldLengthLimit;
        $this->fieldCountLimit = $fieldCountLimit;

        if ($type !== null && strncmp($type, "application/x-www-form-urlencoded", \strlen("application/x-www-form-urlencoded"))) {
            if (!preg_match('#^\s*multipart/(?:form-data|mixed)(?:\s*;\s*boundary\s*=\s*("?)([^"]*)\1)?$#', $type, $matches)) {
                $this->parsePromise = new Success(new Form([]));

                return;
            }

            $this->boundary = $matches[2];
        }
    }

    public function parseForm(): Promise {
        if ($this->parsePromise) {
            return $this->parsePromise;
        }

        $this->parsePromise = call(function () {
            return $this->parseBody(yield $this->body->buffer());
        });

        return $this->parsePromise;
    }

    private function parseBody(string $data): Form {
        // If we end up here, we haven't parsed anything at all yet, so do a quick parse.

        // If there's no boundary, we're in urlencoded mode.
        if ($this->boundary === null) {
            $fields = [];
            $fieldCount = 0;

            foreach (explode("&", $data) as $pair) {
                if (++$fieldCount === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                $pair = explode("=", $pair, 2);
                $field = urldecode($pair[0]);
                $value = urldecode($pair[1] ?? "");

                if (\strlen($value) > $this->fieldLengthLimit) {
                    throw new ParseException("Maximum field length exceeded");
                }

                $fields[$field][] = new Field($field, $value);
            }

            return new Form($fields);
        }

        $fields = [];

        // RFC 7578, RFC 2046 Section 5.1.1
        if (strncmp($data, "--$this->boundary\r\n", \strlen($this->boundary) + 4) !== 0) {
            return new Form([]);
        }

        $exp = explode("\r\n--$this->boundary\r\n", $data);
        $exp[0] = substr($exp[0], \strlen($this->boundary) + 4);
        $exp[\count($exp) - 1] = substr(end($exp), 0, -\strlen($this->boundary) - 8);

        foreach ($exp as $entry) {
            list($rawHeaders, $text) = explode("\r\n\r\n", $entry, 2);
            $headers = [];

            foreach (explode("\r\n", $rawHeaders) as $header) {
                $split = explode(":", $header, 2);
                if (!isset($split[1])) {
                    return new Form([]);
                }
                $headers[strtolower($split[0])] = trim($split[1]);
            }

            $count = preg_match(
                '#^\s*form-data(?:\s*;\s*(?:name\s*=\s*"([^"]+)"|filename\s*=\s*"([^"]+)"))+\s*$#',
                $headers["content-disposition"] ?? "",
                $matches
            );

            if (!$count || !isset($matches[1])) {
                return new Form([]);
            }

            // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

            $name = $matches[1];

            if (isset($matches[2])) {
                $attributes = new FieldAttributes($headers["content-type"] ?? "text/plain", $matches[2]);
            } elseif (isset($headers["content-type"])) {
                $attributes = new FieldAttributes($headers["content-type"]);
            } else {
                $attributes = new FieldAttributes;
            }

            $fields[$name][] = new Field($name, $text, $attributes);
        }

        return new Form($fields);
    }
}
