<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Http\Server\Request;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Pipeline;
use Amp\Pipeline\Queue;
use Revolt\EventLoop;

final class StreamingParser
{
    /** @var int Prevent requests from creating arbitrary many fields causing lot of processing time */
    private readonly int $fieldCountLimit;

    public function __construct(?int $fieldCountLimit = null)
    {
        $this->fieldCountLimit = $fieldCountLimit ?? (int) \ini_get('max_input_vars') ?: 1000;
    }

    /**
     * @return ConcurrentIterator<StreamedField>
     */
    public function parseForm(Request $request): ConcurrentIterator
    {
        $boundary = parseContentBoundary($request->getHeader('content-type') ?? '');
        if ($boundary === null) {
            return Pipeline::fromIterable([])->getIterator();
        }

        $source = new Queue();
        $pipeline = $source->pipe();

        EventLoop::queue(function () use ($boundary, $source, $request): void {
            try {
                if ($boundary !== '') {
                    $this->incrementalBoundaryParse($source, $request->getBody(), $boundary);
                } else {
                    $this->incrementalFieldParse($source, $request->getBody());
                }

                $source->complete();
            } catch (\Throwable $e) {
                $source->error($e);
            }
        });

        return $pipeline->getIterator();
    }

    private function incrementalBoundaryParse(Queue $source, ReadableStream $body, string $boundary): void
    {
        $fieldCount = 0;
        $queue = null;

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
            $end = 0; // For Psalm

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

                $queue = new Queue();
                $stream = new ReadableIterableStream($queue->iterate());
                $field = new StreamedField(
                    $fieldName,
                    $stream,
                    $headerMap["content-type"][0] ?? "text/plain",
                    $matches[2] ?? null,
                    $headers
                );

                $future = $source->pushAsync($field);

                $buffer = \substr($buffer, $end + 4);

                while (($end = \strpos($buffer, $boundarySeparator)) === false) {
                    if (\strlen($buffer) > \strlen($boundarySeparator)) {
                        $position = \strlen($buffer) - \strlen($boundarySeparator);
                        $queue->push(\substr($buffer, 0, $position));
                        $buffer = \substr($buffer, $position);
                    }

                    $buffer .= $chunk = $body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                $queue->push(\substr($buffer, 0, $end));
                $queue->complete();
                $queue = null;
                $offset = $end + \strlen($boundarySeparator);

                while (\strlen($buffer) < 4) {
                    $buffer .= $chunk = $body->read();

                    if ($chunk === null) {
                        throw new ParseException("Request body ended unexpectedly");
                    }
                }

                $future->await();
            }
        } catch (\Throwable $e) {
            $queue?->error($e);
            throw $e;
        }
    }

    private function incrementalFieldParse(Queue $source, ReadableStream $body): void
    {
        $fieldCount = 0;
        $queue = null;

        try {
            $buffer = "";
            $nextPos = 0; // For Psalm

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

                    $queue = new Queue();

                    if ($fieldCount++ === $this->fieldCountLimit) {
                        throw new ParseException("Maximum number of variables exceeded");
                    }

                    $future = $source->pushAsync(new StreamedField(
                        $fieldName,
                        new ReadableIterableStream($queue->iterate())
                    ));

                    while (false === ($nextPos = \strpos($buffer, "&"))) {
                        $chunk = $body->read();

                        if ($chunk === null) {
                            try {
                                $queue->push(\urldecode($buffer));
                            } catch (DisposedException) {
                                // Ignore, we've now completed anyway.
                            }
                            $queue->complete();
                            $queue = null;

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

                        try {
                            $queue->push(\urldecode($chunk));
                        } catch (DisposedException) {
                            // Ignore and continue consuming this field.
                        }
                    }

                    try {
                        $queue->push(\urldecode(\substr($buffer, 0, $nextPos)));
                    } catch (DisposedException) {
                        // Ignore, we need to keep consuming data until the next field.
                    }
                    $queue->complete();
                    $queue = null;

                    $buffer = \substr($buffer, $nextPos + 1);

                    $future->await();
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

                $source->push(new StreamedField($fieldName));

                goto parse_parameter;
            }

            if ($buffer) {
                if ($fieldCount + 1 === $this->fieldCountLimit) {
                    throw new ParseException("Maximum number of variables exceeded");
                }

                $source->push(new StreamedField(\urldecode($buffer)));
            }
        } catch (\Throwable $e) {
            $queue?->error($e);
            throw $e;
        }
    }
}
