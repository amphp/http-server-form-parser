<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\Payload;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Http\Message;

final class StreamedField implements ReadableStream
{
    private readonly Message $message;

    private readonly Payload $payload;

    /**
     * @param array<int, array{string, string} $rawHeaders Headers produced by
     * {@see \Amp\Http\Rfc7230::parseRawHeaders()}
     */
    public function __construct(
        private readonly string $name,
        ?ReadableStream $stream = null,
        private readonly string $mimeType = "text/plain",
        private readonly ?string $filename = null,
        array $rawHeaders = [],
    ) {
        $this->payload = new Payload($stream ?? new ReadableBuffer());
        $this->message = new Internal\FieldMessage($rawHeaders);
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

    public function read(?Cancellation $cancellation = null): ?string
    {
        return $this->payload->read($cancellation);
    }

    public function isReadable(): bool
    {
        return $this->payload->isReadable();
    }

    public function isClosed(): bool
    {
        return $this->payload->isClosed();
    }

    public function close(): void
    {
        $this->payload->close();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->payload->onClose($onClose);
    }

    /**
     * @see Payload::buffer()
     *
     * @throws BufferException|StreamException
     */
    public function buffer(?Cancellation $cancellation = null, int $limit = \PHP_INT_MAX): string
    {
        return $this->payload->buffer($cancellation, $limit);
    }
}
