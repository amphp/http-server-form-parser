<?php

namespace Amp\Http\Server\FormParser;

final class Field {
    /** @var string */
    private $name;

    /** @var string */
    private $value;

    /** @var FieldAttributes */
    private $attributes;

    public function __construct(string $name, string $value = "", FieldAttributes $attributes = null) {
        $this->name = $name;
        $this->value = $value;
        $this->attributes = $attributes ?? new FieldAttributes;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function hasValue(): bool {
        return $this->value !== "";
    }

    public function getAttributes(): FieldAttributes {
        return $this->attributes;
    }
}
