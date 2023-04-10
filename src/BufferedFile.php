<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\Http\HttpMessage;

final class BufferedFile
{
    private readonly HttpMessage $message;

    /**
     * @param list<array{non-empty-string, string}> $rawHeaders Headers produced by
     * {@see \Amp\Http\Http1\Rfc7230::parseHeaderPairs()}
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
