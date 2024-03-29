<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Http1\Rfc7230;
use Amp\Http\HttpMessage;

/**
 * @psalm-import-type HeaderPairsType from HttpMessage
 * @psalm-import-type HeaderMapType from HttpMessage
 */
final class BufferedFile
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly HttpMessage $message;

    /**
     * @param HeaderPairsType $headerPairs Headers produced by {@see Rfc7230::parseHeaderPairs()}.
     */
    public function __construct(
        private readonly string $name,
        private readonly string $contents = "",
        private readonly string $mimeType = "text/plain",
        array $headerPairs = [],
    ) {
        $this->message = new Internal\FieldMessage($headerPairs);
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

    /**
     * @return HeaderMapType
     *
     * @see HttpMessage::getHeaders()
     */
    public function getHeaders(): array
    {
        return $this->message->getHeaders();
    }

    /**
     * @return HeaderPairsType
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
}
