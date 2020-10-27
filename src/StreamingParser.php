<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\PipelineStream;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Http\Server\Request;
use Amp\Pipeline;
use Amp\PipelineSource;
use function Amp\await;
use function Amp\defer;

final class StreamingParser
{
    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private int $fieldCountLimit;

    public function __construct(int $fieldCountLimit = null)
    {
        $this->fieldCountLimit = $fieldCountLimit ?? (int) \ini_get('max_input_vars') ?: 1000;
    }

    public function parseForm(Request $request): Pipeline
    {
        $type = $request->getHeader("content-type");
        $boundary = null;

        if ($type !== null && \strncmp($type, "application/x-www-form-urlencoded", \strlen("application/x-www-form-urlencoded"))) {
            if (!\preg_match('#^\s*multipart/(?:form-data|mixed)(?:\s*;\s*boundary\s*=\s*("?)([^"]*)\1)?$#', $type, $matches)) {
                return Pipeline\fromIterable([]);
            }

            $boundary = $matches[2];
        }

        $body = $request->getBody();

        $source = new PipelineSource;
        $pipeline = $source->pipe();

        defer(function () use ($boundary, $source, $body): void {
            try {
                if ($boundary !== null) {
                    $this->incrementalBoundaryParse($source, $body, $boundary);
                } else {
                    $this->incrementalFieldParse($source, $body);
                }

                $source->complete();
            } catch (\Throwable $e) {
                $source->fail($e);
            } finally {
                $source = null;
            }
        });

        return $pipeline;
    }

    /**
     * @param PipelineSource $source
     * @param InputStream    $body
     * @param string         $boundary
     *
     * @throws \Throwable
     */
    private function incrementalBoundaryParse(PipelineSource $source, InputStream $body, string $boundary): void
    {
        $fieldCount = 0;

        try {
            $buffer = "";

            // RFC 7578, RFC 2046 Section 5.1.1
            $boundarySeparator = "--{$boundary}";
            while (\strlen($buffer) < \strlen($boundarySeparator) + 4) {
                $buffer .= $chunk = $body->read();

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
                    $buffer .= $chunk = $body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                if ($fieldCount++ === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                try {
                    $headers = Rfc7230::parseRawHeaders(\substr($buffer, $offset, $end + 2 - $offset));
                } catch (InvalidHeaderException $e) {
                    throw new ParseException("Invalid headers in body part", 0, $e);
                }

                $headerMap = [];
                foreach ($headers as [$key, $value]) {
                    $headerMap[\strtolower($key)][] = $value;
                }

                $count = \preg_match(
                    '#^\s*form-data(?:\s*;\s*(?:name\s*=\s*"([^"]+)"|filename\s*=\s*"([^"]+)"))+\s*$#',
                    $headerMap["content-disposition"][0] ?? "",
                    $matches
                );

                if (!$count || !isset($matches[1])) {
                    throw new ParseException("Invalid content-disposition header within multipart form");
                }

                $fieldName = $matches[1];

                // Ignore Content-Transfer-Encoding as deprecated and hence we won't support it

                $dataEmitter = new PipelineSource;
                $stream = new PipelineStream($dataEmitter->pipe());
                $field = new StreamedField(
                    $fieldName,
                    $stream,
                    $headerMap["content-type"][0] ?? "text/plain",
                    $matches[2] ?? null,
                    $headers
                );

                $emitPromise = $source->emit($field);

                $buffer = \substr($buffer, $end + 4);

                while (($end = \strpos($buffer, $boundarySeparator)) === false) {
                    if (\strlen($buffer) > \strlen($boundarySeparator)) {
                        $position = \strlen($buffer) - \strlen($boundarySeparator);
                        $dataEmitter->yield(\substr($buffer, 0, $position));
                        $buffer = \substr($buffer, $position);
                    }

                    $buffer .= $chunk = $body->read();

                    if ($chunk === null) {
                        $e = new ParseException("Request body ended unexpectedly");
                        $dataEmitter->fail($e);
                        throw $e;
                    }
                }

                $dataEmitter->yield(\substr($buffer, 0, $end));
                $dataEmitter->complete();
                $dataEmitter = null;
                $offset = $end + \strlen($boundarySeparator);

                while (\strlen($buffer) < 4) {
                    $buffer .= $chunk = $body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                await($emitPromise);
            }
        } catch (\Throwable $e) {
            if (isset($dataEmitter)) {
                $dataEmitter->fail($e);
            }

            throw $e;
        }
    }

    /**
     * @param PipelineSource $source
     * @param InputStream    $body
     *
     * @throws \Throwable
     */
    private function incrementalFieldParse(PipelineSource $source, InputStream $body): void
    {
        $fieldCount = 0;

        try {
            $buffer = "";

            while (null !== $chunk = $body->read()) {
                if ($chunk === "") {
                    continue;
                }

                $buffer .= $chunk;

                parse_parameter:

                $equalPos = \strpos($buffer, "=");
                if ($equalPos !== false) {
                    $fieldName = \urldecode(\substr($buffer, 0, $equalPos));
                    $buffer = \substr($buffer, $equalPos + 1);

                    $dataEmitter = new PipelineSource;

                    if ($fieldCount++ === $this->fieldCountLimit) {
                        throw new ParseException("Maximum number of variables exceeded");
                    }

                    $emitPromise = $source->emit(new StreamedField(
                        $fieldName,
                        new PipelineStream($dataEmitter->pipe())
                    ));

                    while (false === ($nextPos = \strpos($buffer, "&"))) {
                        $chunk = $body->read();

                        if ($chunk === null) {
                            $dataEmitter->yield(\urldecode($buffer));
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

                        $dataEmitter->yield(\urldecode($chunk));
                    }

                    $dataEmitter->yield(\urldecode(\substr($buffer, 0, $nextPos)));
                    $dataEmitter->complete();
                    $dataEmitter = null;

                    $buffer = \substr($buffer, $nextPos + 1);

                    await($emitPromise);

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

                $source->yield(new StreamedField($fieldName));

                goto parse_parameter;
            }

            if ($buffer) {
                if ($fieldCount + 1 === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                $source->yield(new StreamedField(\urldecode($buffer)));
            }
        } catch (\Throwable $e) {
            if (isset($dataEmitter)) {
                $dataEmitter->fail($e);
            }

            throw $e;
        }
    }
}
