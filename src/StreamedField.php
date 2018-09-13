<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\Payload;

final class StreamedField extends Payload
{
    /** @var string */
    private $name;

    /** @var string */
    private $mimeType;

    /** @var string */
    private $filename;

    /**
     * @param string           $name
     * @param InputStream|null $stream
     * @param string           $mimeType
     * @param string|null      $filename
     */
    public function __construct(string $name, InputStream $stream = null, string $mimeType = "text/plain", string $filename = null)
    {
        parent::__construct($stream ?? new InMemoryStream);
        $this->name = $name;
        $this->mimeType = $mimeType;
        $this->filename = $filename;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function isFile(): bool
    {
        return $this->filename !== null;
    }
}
