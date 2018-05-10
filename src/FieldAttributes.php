<?php

namespace Amp\Http\Server\FormParser;

final class FieldAttributes {
    /** @var string */
    private $mimeType;

    /** @var string|null */
    private $filename;

    public function __construct(string $mimeType = "text/plain", string $filename = null) {
        $this->mimeType = $mimeType;
        $this->filename = $filename;
    }

    public function getMimeType(): string {
        return $this->mimeType;
    }

    public function isFile(): bool {
        return $this->filename !== null;
    }

    public function getFilename() {
        return $this->filename;
    }
}
