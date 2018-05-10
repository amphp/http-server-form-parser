<?php

namespace Amp\Http\Server\FormParser;

final class Form {
    /** @var Field[][] */
    private $fields;

    /** @var string[] */
    private $names;

    /**
     * @param Field[][] $fields
     */
    public function __construct(array $fields) {
        $this->fields = $fields;
    }

    /**
     * Get first field value with a given name or null, if no such field exists.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getValue(string $name) {
        if (!isset($this->fields[$name][0])) {
            return null;
        }

        return $this->fields[$name][0]->getValue();
    }

    /**
     * Get all field values with a given name.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getValueArray(string $name): array {
        $values = [];

        foreach ($this->fields[$name] ?? [] as $field) {
            $values[] = $field->getValue();
        }

        return $values;
    }

    public function hasField(string $name): bool {
        return isset($this->fields[$name][0]);
    }

    /**
     * Get first field with a given name or null, if no such field exists.
     *
     * @param string $name
     *
     * @return Field|null
     */
    public function getField(string $name) {
        return $this->fields[$name][0] ?? null;
    }

    /**
     * Get all fields with a given name.
     *
     * @param string $name
     *
     * @return Field[]
     */
    public function getFieldArray(string $name): array {
        return $this->fields[$name] ?? [];
    }

    /**
     * Returns the names of the passed fields.
     *
     * @return string[]
     */
    public function getNames(): array {
        return $this->names ?? $this->names = \array_map("strval", \array_keys($this->fields));
    }
}
