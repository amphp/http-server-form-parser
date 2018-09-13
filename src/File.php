<?php

namespace Amp\Http\Server\FormParser;

final class File
{
    /** @var string */
    private $name;

    /** @var string */
    private $contents;

    /** @var string */
    private $mimeType;

    public function __construct(string $name, string $value = "", string $mimeType = "text/plain")
    {
        $this->name = $name;
        $this->contents = $value;
        $this->mimeType = $mimeType;
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
}
