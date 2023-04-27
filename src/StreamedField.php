<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\Payload;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Http1\Rfc7230;
use Amp\Http\HttpMessage;

/**
 * @implements \IteratorAggregate<int, string>
 */
final class StreamedField implements ReadableStream, \IteratorAggregate
{
    use ForbidCloning;
    use ForbidSerialization;
    use ReadableStreamIteratorAggregate;

    private readonly HttpMessage $message;

    private readonly Payload $payload;

    /**
     * @param list<array{non-empty-string, string}> $headerPairs Headers produced by
     * {@see Rfc7230::parseHeaderPairs()}
     */
    public function __construct(
        private readonly string $name,
        ?ReadableStream $stream = null,
        private readonly string $mimeType = "text/plain",
        private readonly ?string $filename = null,
        array $headerPairs = [],
    ) {
        $this->payload = new Payload($stream ?? new ReadableBuffer());
        $this->message = new Internal\FieldMessage($headerPairs);
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

    /**
     * @return array<non-empty-string, list<string>>
     *
     * @see HttpMessage::getHeaders()
     */
    public function getHeaders(): array
    {
        return $this->message->getHeaders();
    }

    /**
     * @return list<array{non-empty-string, string}>
     *
     * @see HttpMessage::getHeaderPairs()
     */
    public function getHeaderPairs(): array
    {
        return $this->message->getHeaderPairs();
    }

    /**
     * @see HttpMessage::getHeader()
     */
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
