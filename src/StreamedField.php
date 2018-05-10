<?php

namespace Amp\Http\Server\FormParser;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\Payload;

final class StreamedField extends Payload {
    /** @var string */
    private $name;

    /** @var FieldAttributes|null */
    private $attributes;

    /**
     * @param string               $name
     * @param InputStream|null     $stream
     * @param FieldAttributes|null $attributes
     */
    public function __construct(string $name, InputStream $stream = null, FieldAttributes $attributes = null) {
        parent::__construct($stream ?? new InMemoryStream);
        $this->name = $name;
        $this->attributes = $attributes;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getAttributes(): FieldAttributes {
        return $this->attributes ?? $this->attributes = new FieldAttributes;
    }
}
