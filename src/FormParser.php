<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\StreamException;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Http1\Rfc7230;
use Amp\Http\HttpStatus;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;

/**
 * This class parses submitted forms from incoming request bodies in application/x-www-form-urlencoded and
 * multipart/form-data format.
 */
final class FormParser
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private readonly int $fieldCountLimit;

    public function __construct(?int $fieldCountLimit = null)
    {
        $this->fieldCountLimit = $fieldCountLimit ?? (int) (\ini_get('max_input_vars') ?: 1000);
    }

    /**
     * Consumes the request's body and parses it.
     *
     * If the content-type doesn't match the supported form content types, the body isn't consumed.
     *
     * @throws HttpErrorException
     */
    public function parseForm(Request $request): Form
    {
        $boundary = parseContentBoundary($request->getHeader('content-type') ?? '');

        // Don't consume body if we don't have a form content type
        try {
            $body = $boundary === null ? '' : $request->getBody()->buffer();
        } catch (StreamException) {
            throw new HttpErrorException(HttpStatus::BAD_REQUEST, "Request body ended unexpectedly");
        }

        return $this->parseBody($body, $boundary);
    }

    /**
     * @param string $body application/x-www-form-urlencoded or multipart/form-data body.
     * @param string|null $boundary Result from {@see parseContentBoundary()} from a content-type header.
     *
     * @throws HttpErrorException
     */
    public function parseBody(string $body, ?string $boundary): Form
    {
        return match ($boundary) {
            null => new Form([]),
            '' => $this->parseUrlEncodedBody($body),
            default => $this->parseMultipartBody($body, $boundary),
        };
    }

    /**
     * @param string $body application/x-www-form-urlencoded body.
     */
    public function parseUrlEncodedBody(string $body): Form
    {
        $pair = [];
        $fields = [];

        foreach (\explode("&", $body, $this->fieldCountLimit) as $pair) {
            $pair = \explode("=", $pair, 2);
            $field = \urldecode($pair[0]);
            $value = \urldecode($pair[1] ?? "");

            if ($field === '') {
                throw new HttpErrorException(HttpStatus::BAD_REQUEST, "Empty field name in form data");
            }

            $fields[$field][] = $value;
        }

        if (\str_contains($pair[1] ?? "", "&")) {
            throw new HttpErrorException(HttpStatus::BAD_REQUEST, "Maximum number of variables exceeded");
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
        $exp[0] = \substr($exp[0] ?? "", \strlen($boundary) + 4);
        $exp[\count($exp) - 1] = \substr(\end($exp), 0, -\strlen($boundary) - 8);

        $entry = ''; // For Psalm

        foreach ($exp as $entry) {
            if (($position = \strpos($entry, "\r\n\r\n")) === false) {
                throw new HttpErrorException(HttpStatus::BAD_REQUEST, "No header/body boundary found");
            }

            try {
                $headers = Rfc7230::parseHeaderPairs(\substr($entry, 0, $position + 2));
            } catch (InvalidHeaderException) {
                throw new HttpErrorException(HttpStatus::BAD_REQUEST, "Invalid headers in body part");
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
                throw new HttpErrorException(HttpStatus::BAD_REQUEST, "Missing or invalid content disposition");
            }

            // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

            /** @var non-empty-string $name */
            $name = $matches[1];
            $contentType = $headerMap["content-type"][0] ?? "text/plain";

            if (isset($matches[2])) {
                $files[$name][] = new BufferedFile($matches[2], $entry, $contentType, $headers);
            } else {
                $fields[$name][] = $entry;
            }
        }

        if (\str_contains($entry, "--$boundary")) {
            throw new HttpErrorException(HttpStatus::BAD_REQUEST, "Maximum number of variables exceeded");
        }

        return new Form($fields, $files);
    }
}
