<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\IteratorStream;
use Amp\Emitter;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestBody;
use Amp\Iterator;
use function Amp\asyncCall;

final class StreamingParser {
    const DEFAULT_FIELD_LENGTH_LIMIT = 16384;
    const DEFAULT_FIELD_COUNT_LIMIT = 200;

    /** @var Emitter */
    private $emitter;

    /** @var Iterator */
    private $iterator;

    /** @var RequestBody */
    private $body;

    /** @var string|null */
    private $boundary;

    /** @var int Prevent buffering of arbitrary long names and fail instead */
    private $fieldLengthLimit;

    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private $fieldCountLimit;

    /** @var int Current field count */
    private $fieldCount = 0;

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
                $this->iterator = Iterator\fromIterable([]);

                return;
            }

            $this->boundary = $matches[2];
        }
    }

    public function parseForm(): Iterator {
        if ($this->iterator) {
            throw new \Error("Parsing can only be started once.");
        }

        $this->emitter = new Emitter;
        $this->iterator = $this->emitter->iterate();

        asyncCall(function () {
            try {
                if ($this->boundary) {
                    yield from $this->incrementalBoundaryParse();
                } else {
                    yield from $this->incrementalFieldParse();
                }

                $this->emitter->complete();
            } catch (\Throwable $e) {
                $this->emitter->fail($e);
            } finally {
                $this->emitter = null;
            }
        });

        return $this->iterator;
    }

    /**
     * @param string $fieldName
     *
     * @throws ParseException
     */
    private function checkFieldLimits(string $fieldName) {
        if ($this->fieldCount++ === $this->fieldCountLimit) {
            throw new ParseException("Maximum number of variables exceeded");
        }

        if (\strlen($fieldName) > $this->fieldLengthLimit) {
            throw new ParseException("Maximum field length exceeded");
        }
    }

    /**
     * @return \Generator
     *
     * @throws ParseException
     */
    private function incrementalBoundaryParse(): \Generator {
        try {
            $buffer = "";

            // RFC 7578, RFC 2046 Section 5.1.1
            $boundarySeparator = "--{$this->boundary}";
            while (\strlen($buffer) < \strlen($boundarySeparator) + 4) {
                $buffer .= $chunk = yield $this->body->read();

                if ($chunk === null) {
                    throw new ParseException("Request body ended unexpectedly");
                }
            }

            $offset = \strlen($boundarySeparator);
            if (strncmp($buffer, $boundarySeparator, $offset)) {
                throw new ParseException("Invalid boundary");
            }

            $boundarySeparator = "\r\n$boundarySeparator";

            while (substr_compare($buffer, "--\r\n", $offset)) {
                $offset += 2;

                while (($end = strpos($buffer, "\r\n\r\n", $offset)) === false) {
                    $buffer .= $chunk = yield $this->body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                $headers = [];

                foreach (\explode("\r\n", substr($buffer, $offset, $end - $offset)) as $header) {
                    $split = \explode(":", $header, 2);

                    if (!isset($split[1])) {
                        throw new ParseException("Invalid content header within multipart form");
                    }

                    $headers[\strtolower($split[0])] = \trim($split[1]);
                }

                $count = preg_match(
                    '#^\s*form-data(?:\s*;\s*(?:name\s*=\s*"([^"]+)"|filename\s*=\s*"([^"]+)"))+\s*$#',
                    $headers["content-disposition"] ?? "",
                    $matches
                );

                if (!$count || !isset($matches[1])) {
                    throw new ParseException("Invalid content-disposition header within multipart form");
                }

                $fieldName = $matches[1];

                // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

                if (isset($matches[2])) {
                    $fieldAttributes = new FieldAttributes($headers["content-type"] ?? "text/plain", $matches[2]);
                } elseif (isset($headers["content-type"])) {
                    $fieldAttributes = new FieldAttributes($headers["content-type"]);
                } else {
                    $fieldAttributes = new FieldAttributes;
                }

                $this->checkFieldLimits($fieldName);

                $dataEmitter = new Emitter;
                $stream = new IteratorStream($dataEmitter->iterate());
                $field = new StreamedField($fieldName, $stream, $fieldAttributes);

                $emitPromise = $this->emitter->emit($field);

                $buffer = \substr($buffer, $end + 4);
                $offset = 0;

                while (($end = \strpos($buffer, $boundarySeparator, $offset)) === false) {
                    $buffer .= $chunk = yield $this->body->read();

                    if ($chunk === null) {
                        $e = new ParseException("Request body ended unexpectedly");
                        $dataEmitter->fail($e);
                        throw $e;
                    }

                    if (\strlen($buffer) > \strlen($boundarySeparator)) {
                        $offset = \strlen($buffer) - \strlen($boundarySeparator);
                        yield $dataEmitter->emit(\substr($buffer, 0, $offset));
                        $buffer = \substr($buffer, $offset);
                    }
                }

                yield $dataEmitter->emit(\substr($buffer, 0, $end));
                $dataEmitter->complete();
                $dataEmitter = null;
                $offset = $end + \strlen($boundarySeparator);

                while (\strlen($buffer) < 4) {
                    $buffer .= $chunk = yield $this->body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                yield $emitPromise;
            }
        } catch (\Throwable $e) {
            if (isset($dataEmitter)) {
                $dataEmitter->fail($e);
            }

            throw $e;
        }
    }

    /**
     * @return \Generator
     *
     * @throws ParseException
     */
    private function incrementalFieldParse(): \Generator {
        try {
            $buffer = "";

            while (null !== $chunk = yield $this->body->read()) {
                if ($chunk === "") {
                    continue;
                }

                $buffer .= $chunk;

                parse_parameter:

                $equalPos = \strpos($buffer, "=");
                if ($equalPos !== false) {
                    $fieldName = \urldecode(\substr($buffer, 0, $equalPos));
                    $buffer = \substr($buffer, $equalPos + 1);

                    $dataEmitter = new Emitter;

                    $emitPromise = $this->emitter->emit(new StreamedField(
                        $fieldName,
                        new IteratorStream($dataEmitter->iterate())
                    ));

                    while (false === ($nextPos = \strpos($buffer, "&"))) {
                        $chunk = yield $this->body->read();

                        if ($chunk === null) {
                            yield $dataEmitter->emit(\urldecode($buffer));
                            $dataEmitter->complete();
                            $dataEmitter = null;

                            return;
                        }

                        $buffer .= $chunk;

                        if (\strlen($buffer) > $this->fieldLengthLimit) {
                            throw new ParseException("Maximum field length exceeded");
                        }

                        $lastEncodedPos = \strrpos($buffer, "%", -2);
                        $chunk = $buffer;

                        if ($lastEncodedPos !== false) {
                            $chunk = \substr($chunk, 0, $lastEncodedPos);
                            $buffer = \substr($buffer, $lastEncodedPos);
                        } else {
                            $buffer = "";
                        }

                        yield $dataEmitter->emit(\urldecode($chunk));
                    }

                    yield $dataEmitter->emit(\urldecode(\substr($buffer, 0, $nextPos)));
                    $dataEmitter->complete();
                    $dataEmitter = null;

                    $buffer = \substr($buffer, $nextPos + 1);

                    yield $emitPromise;

                    goto parse_parameter;
                }

                $nextPos = \strpos($buffer, "&");
                if ($nextPos === false) {
                    if (\strlen($buffer) > $this->fieldLengthLimit) {
                        throw new ParseException("Maximum field length exceeded");
                    }

                    continue;
                }

                $fieldName = \urldecode(\substr($buffer, 0, $nextPos));
                $buffer = \substr($buffer, $nextPos + 1);

                yield $this->emitter->emit(new StreamedField($fieldName));

                goto parse_parameter;
            }

            if ($buffer) {
                yield $this->emitter->emit(new StreamedField(\urldecode($buffer)));
            }
        } catch (\Throwable $e) {
            if (isset($dataEmitter)) {
                $dataEmitter->fail($e);
            }

            throw $e;
        }
    }
}
