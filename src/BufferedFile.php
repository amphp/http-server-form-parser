<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Message;

final class BufferedFile extends Message
{
    private string $name;

    private string $contents;

    private string $mimeType;

    /**
     * @param string $name
     * @param string $value
     * @param string $mimeType
     * @param array  $rawHeaders Headers produced by {@see \Amp\Http\Rfc7230::parseRawHeaders()}
     */
    public function __construct(string $name, string $value = "", string $mimeType = "text/plain", array $rawHeaders = [])
    {
        $this->name = $name;
        $this->contents = $value;
        $this->mimeType = $mimeType;

        foreach ($rawHeaders as [$key, $value]) {
            $this->addHeader($key, $value);
        }
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
