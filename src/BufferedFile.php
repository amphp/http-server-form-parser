<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Message;

final class BufferedFile
{
    private readonly Message $message;

    /**
     * @param array<int, array{string, string} $rawHeaders Headers produced by
     * {@see \Amp\Http\Rfc7230::parseRawHeaders()}
     */
    public function __construct(
        private readonly string $name,
        private readonly string $contents = "",
        private readonly string $mimeType = "text/plain",
        array $rawHeaders = [],
    ) {
        $this->message = new Internal\FieldMessage($rawHeaders);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function isEmpty(): bool
    {
        return $this->contents === "";
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
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