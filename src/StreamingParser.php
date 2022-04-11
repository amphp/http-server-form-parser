<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\Emitter;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Http\Server\Request;
use Amp\Iterator;
use function Amp\asyncCall;

final class StreamingParser
{
    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private $fieldCountLimit;

    public function __construct(int $fieldCountLimit = null)
    {
        $this->fieldCountLimit = $fieldCountLimit ?? (int) \ini_get('max_input_vars') ?: 1000;
    }

    public function parseForm(Request $request): Iterator
    {
        $boundary = parseContentBoundary($request->getHeader('content-type') ?? '');
        if ($boundary === null) {
            return Iterator\fromIterable([]);
        }

        $body = $request->getBody();

        $emitter = new Emitter;
        $iterator = $emitter->iterate();

        asyncCall(function () use ($boundary, $emitter, $body) {
            try {
                if ($boundary !== '') {
                    yield from $this->incrementalBoundaryParse($emitter, $body, $boundary);
                } else {
                    yield from $this->incrementalFieldParse($emitter, $body);
                }

                $emitter->complete();
            } catch (\Throwable $e) {
                $emitter->fail($e);
            } finally {
                $emitter = null;
            }
        });

        return $iterator;
    }

    /**
     * @param Emitter     $emitter
     * @param InputStream $body
     * @param string      $boundary
     *
     * @return \Generator
     * @throws \Throwable
     */
    private function incrementalBoundaryParse(Emitter $emitter, InputStream $body, string $boundary): \Generator
    {
        $fieldCount = 0;

        try {
            $buffer = "";

            // RFC 7578, RFC 2046 Section 5.1.1
            $boundarySeparator = "--{$boundary}";
            while (\strlen($buffer) < \strlen($boundarySeparator) + 4) {
                $buffer .= $chunk = yield $body->read();

                if ($chunk === null) {
                    throw new ParseException("Request body ended unexpectedly");
                }
            }

            $offset = \strlen($boundarySeparator);
            if (\strncmp($buffer, $boundarySeparator, $offset)) {
                throw new ParseException("Invalid boundary");
            }

            $boundarySeparator = "\r\n$boundarySeparator";

            while (\substr_compare($buffer, "--\r\n", $offset)) {
                $offset += 2;

                while (($end = \strpos($buffer, "\r\n\r\n", $offset)) === false) {
                    $buffer .= $chunk = yield $body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                if ($fieldCount++ === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                try {
                    $headers = Rfc7230::parseHeaders(\substr($buffer, $offset, $end + 2 - $offset));
                } catch (InvalidHeaderException $e) {
                    throw new ParseException("Invalid headers in body part", 0, $e);
                }

                $count = \preg_match(
                    '#^\s*form-data(?:\s*;\s*(?:name\s*=\s*"([^"]+)"|filename\s*=\s*"([^"]+)"))+\s*$#',
                    $headers["content-disposition"][0] ?? "",
                    $matches
                );

                if (!$count || !isset($matches[1])) {
                    throw new ParseException("Invalid content-disposition header within multipart form");
                }

                $fieldName = $matches[1];

                // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

                $dataEmitter = new Emitter;
                $stream = new IteratorStream($dataEmitter->iterate());
                $field = new StreamedField($fieldName, $stream, $headers["content-type"][0] ?? "text/plain", $matches[2] ?? null);

                $emitPromise = $emitter->emit($field);

                $buffer = \substr($buffer, $end + 4);

                while (($end = \strpos($buffer, $boundarySeparator)) === false) {
                    if (\strlen($buffer) > \strlen($boundarySeparator)) {
                        $position = \strlen($buffer) - \strlen($boundarySeparator);
                        yield $dataEmitter->emit(\substr($buffer, 0, $position));
                        $buffer = \substr($buffer, $position);
                    }

                    $buffer .= $chunk = yield $body->read();

                    if ($chunk === null) {
                        $e = new ParseException("Request body ended unexpectedly");
                        $dataEmitter->fail($e);
                        throw $e;
                    }
                }

                yield $dataEmitter->emit(\substr($buffer, 0, $end));
                $dataEmitter->complete();
                $dataEmitter = null;
                $offset = $end + \strlen($boundarySeparator);

                while (\strlen($buffer) < 4) {
                    $buffer .= $chunk = yield $body->read();

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
     * @param Emitter     $emitter
     * @param InputStream $body
     *
     * @return \Generator
     *
     * @throws \Throwable
     */
    private function incrementalFieldParse(Emitter $emitter, InputStream $body): \Generator
    {
        $fieldCount = 0;

        try {
            $buffer = "";

            while (null !== $chunk = yield $body->read()) {
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

                    if ($fieldCount++ === $this->fieldCountLimit) {
                        throw new ParseException("Maximum number of variables exceeded");
                    }

                    $emitPromise = $emitter->emit(new StreamedField(
                        $fieldName,
                        new IteratorStream($dataEmitter->iterate())
                    ));

                    while (false === ($nextPos = \strpos($buffer, "&"))) {
                        $chunk = yield $body->read();

                        if ($chunk === null) {
                            yield $dataEmitter->emit(\urldecode($buffer));
                            $dataEmitter->complete();
                            $dataEmitter = null;

                            return;
                        }

                        $buffer .= $chunk;

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
                    continue;
                }

                $fieldName = \urldecode(\substr($buffer, 0, $nextPos));
                $buffer = \substr($buffer, $nextPos + 1);

                if ($fieldCount++ === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                yield $emitter->emit(new StreamedField($fieldName));

                goto parse_parameter;
            }

            if ($buffer) {
                if ($fieldCount + 1 === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                yield $emitter->emit(new StreamedField(\urldecode($buffer)));
            }
        } catch (\Throwable $e) {
            if (isset($dataEmitter)) {
                $dataEmitter->fail($e);
            }

            throw $e;
        }
    }
}
