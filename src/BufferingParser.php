<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Http\Server\Request;

/**
 * This class parses submitted forms from incoming request bodies in application/x-www-form-urlencoded and
 * multipart/form-data format.
 */
final class BufferingParser
{
    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private readonly int $fieldCountLimit;

    public function __construct(?int $fieldCountLimit = null)
    {
        $this->fieldCountLimit = $fieldCountLimit ?? (int) \ini_get('max_input_vars') ?: 1000;
    }

    /**
     * Consumes the request's body and parses it.
     *
     * If the content-type doesn't match the supported form content types, the body isn't consumed.
     *
     * @param Request $request
     *
     * @return Form
     */
    public function parseForm(Request $request): Form
    {
        $boundary = parseContentBoundary($request->getHeader('content-type') ?? '');
        if ($boundary === null) {
            return new Form([]);
        }

        $body = $request->getBody()->buffer();

        return $boundary === ''
            ? $this->parseUrlEncodedBody($body)
            : $this->parseMultipartBody($body, $boundary);
    }

    /**
     * @param string $body application/x-www-form-urlencoded body.
     */
    public function parseUrlEncodedBody(string $body): Form
    {
        $fields = [];

        foreach (\explode("&", $body, $this->fieldCountLimit) as $pair) {
            $pair = \explode("=", $pair, 2);
            $field = \urldecode($pair[0]);
            $value = \urldecode($pair[1] ?? "");

            $fields[$field][] = $value;
        }

        if (\str_contains($pair[1] ?? "", "&")) {
            throw new ParseException("Maximum number of variables exceeded");
        }

        return new Form($fields);
    }

    /**
     * Parses the given body multipart body string using the given boundary.
     *
     * @param string $body multipart/form-data or multipart/mixed body.
     * @param string $boundary Part boundary identifier.
     */
    public function parseMultipartBody(string $body, string $boundary): Form
    {
        $fields = $files = [];

        // RFC 7578, RFC 2046 Section 5.1.1
        if (\strncmp($body, "--$boundary\r\n", \strlen($boundary) + 4) !== 0) {
            return new Form([]);
        }

        $exp = \explode("\r\n--$boundary\r\n", $body, $this->fieldCountLimit);
        $exp[0] = \substr($exp[0], \strlen($boundary) + 4);
        $exp[\count($exp) - 1] = \substr(\end($exp), 0, -\strlen($boundary) - 8);

        foreach ($exp as $entry) {
            if (($position = \strpos($entry, "\r\n\r\n")) === false) {
                throw new ParseException("No header/body boundary found");
            }

            try {
                $headers = Rfc7230::parseRawHeaders(\substr($entry, 0, $position + 2));
            } catch (InvalidHeaderException $e) {
                throw new ParseException("Invalid headers in body part", 0, $e);
            }

            $headerMap = [];
            foreach ($headers as [$key, $value]) {
                $headerMap[\strtolower($key)][] = $value;
            }

            $entry = \substr($entry, $position + 4);

            $count = \preg_match(
                '#^\s*form-data(?:\s*;\s*(?:name\s*=\s*"([^"]+)"|filename\s*=\s*"([^"]*)"))+\s*$#',
                $headerMap["content-disposition"][0] ?? "",
                $matches
            );

            if (!$count || !isset($matches[1])) {
                throw new ParseException("Missing or invalid content disposition");
            }

            // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

            $name = $matches[1];
            $contentType = $headerMap["content-type"][0] ?? "text/plain";

            if (isset($matches[2])) {
                $files[$name][] = new BufferedFile($matches[2] ?? '', $entry, $contentType, $headers);
            } else {
                $fields[$name][] = $entry;
            }
        }

        if (\str_contains($entry ?? "", "--$boundary")) {
            throw new ParseException("Maximum number of variables exceeded");
        }

        return new Form($fields, $files);
    }
}
