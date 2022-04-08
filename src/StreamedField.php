<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\Payload;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\Http\Message;

final class StreamedField extends Payload
{
    private string $name;

    private string $mimeType;

    private ?string $filename;

    private Message $message;

    /**
     * @param array<int, array{string, string} $rawHeaders Headers produced by
     * {@see \Amp\Http\Rfc7230::parseRawHeaders()}
     */
    public function __construct(
        string $name,
        ReadableStream $stream = null,
        string $mimeType = "text/plain",
        ?string $filename = null,
        array $rawHeaders = []
    ) {
        parent::__construct($stream ?? new ReadableBuffer());
        $this->name = $name;
        $this->mimeType = $mimeType;
        $this->filename = $filename;

        $this->message = new class($rawHeaders) extends Message {
            public function __construct(array $headers)
            {
                foreach ($headers as [$key, $value]) {
                    $this->addHeader($key, $value);
                }
            }
        };
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function isFile(): bool
    {
        return $this->filename !== null;
    }

    public function getHeaders(): array
    {
        return $this->message->getHeaders();
    }

    public function getRawHeaders(): array
    {
        return $this->message->getRawHeaders();
    }

    public function getHeader(string $name): ?string
    {
        return $this->message->getHeader($name);
    }
}
