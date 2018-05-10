<?php

namespace Amp\Http\Server\FormParser;

use Amp\Deferred;
use Amp\Http\Server\Request;
use Amp\Promise;
use Amp\Success;

/**
 * This class parses submitted forms from incoming request bodies in application/x-www-form-urlencoded and
 * multipart/form-data format.
 */
final class BufferingParser {
    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private $fieldCountLimit;

    public function __construct(int $fieldCountLimit = null) {
        $this->fieldCountLimit = $fieldCountLimit ?? (int) \ini_get('max_input_vars') ?: 1000;
    }

    /**
     * Consumes the request's body and parses it.
     *
     * If the content-type doesn't match the supported form content types, the body isn't consumed.
     *
     * @param Request $request
     *
     * @return Promise
     */
    public function parseForm(Request $request): Promise {
        $type = $request->getHeader("content-type");
        $boundary = null;

        if ($type !== null && strncmp($type, "application/x-www-form-urlencoded", \strlen("application/x-www-form-urlencoded"))) {
            if (!preg_match('#^\s*multipart/(?:form-data|mixed)(?:\s*;\s*boundary\s*=\s*("?)([^"]*)\1)?$#', $type, $matches)) {
                return new Success(new Form([]));
            }

            $boundary = $matches[2];
            unset($matches);
        }

        $body = $request->getBody();

        $deferred = new Deferred;

        $body->buffer()->onResolve(function ($error, $value) use ($deferred, $boundary) {
            if ($error) {
                $deferred->fail($error);
            } else {
                try {
                    $deferred->resolve($this->parseBody($value, $boundary));
                } catch (ParseException $e) {
                    $deferred->fail($e);
                }
            }
        });

        return $deferred->promise();
    }

    /**
     * @param string      $body
     * @param string|null $boundary
     *
     * @return Form
     * @throws ParseException
     */
    private function parseBody(string $body, string $boundary = null): Form {
        // If there's no boundary, we're in urlencoded mode.
        if ($boundary === null) {
            $fields = [];
            $fieldCount = 0;

            foreach (\explode("&", $body) as $pair) {
                if (++$fieldCount === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                $pair = \explode("=", $pair, 2);
                $field = \urldecode($pair[0]);
                $value = \urldecode($pair[1] ?? "");

                $fields[$field][] = $value;
            }

            return new Form($fields);
        }

        $fields = $files = [];

        // RFC 7578, RFC 2046 Section 5.1.1
        if (\strncmp($body, "--$boundary\r\n", \strlen($boundary) + 4) !== 0) {
            return new Form([]);
        }

        $exp = \explode("\r\n--$boundary\r\n", $body);
        $exp[0] = \substr($exp[0], \strlen($boundary) + 4);
        $exp[\count($exp) - 1] = \substr(end($exp), 0, -\strlen($boundary) - 8);

        foreach ($exp as $entry) {
            list($rawHeaders, $text) = \explode("\r\n\r\n", $entry, 2);
            $headers = [];

            foreach (\explode("\r\n", $rawHeaders) as $header) {
                $split = \explode(":", $header, 2);
                if (!isset($split[1])) {
                    return new Form([]);
                }
                $headers[\strtolower($split[0])] = \trim($split[1]);
            }

            $count = \preg_match(
                '#^\s*form-data(?:\s*;\s*(?:name\s*=\s*"([^"]+)"|filename\s*=\s*"([^"]+)"))+\s*$#',
                $headers["content-disposition"] ?? "",
                $matches
            );

            if (!$count || !isset($matches[1])) {
                return new Form([]);
            }

            // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

            $name = $matches[1];
            $contentType = $headers["content-type"] ?? "text/plain";

            if (isset($matches[2])) {
                $files[$name][] = new File($matches[2], $text, $contentType);
            } else {
                $fields[$name][] = $text;
            }
        }

        return new Form($fields, $files);
    }
}
