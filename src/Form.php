<?php

namespace Amp\Http\Server\FormParser;

final class Form
{
    /** @var string[][] */
    private $fields;

    /** @var File[][] */
    private $files;

    /** @var string[] */
    private $names;

    /**
     * @param string[][] $fields
     * @param File[][]   $files
     */
    public function __construct(array $fields, array $files = [])
    {
        $this->fields = $fields;
        $this->files = $files;
    }

    /**
     * Gets the first field value with a given name or null, if no such field exists.
     *
     * File fields are not returned by this method.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getValue(string $name)
    {
        if (!isset($this->fields[$name][0])) {
            return null;
        }

        return $this->fields[$name][0];
    }

    /**
     * Gets all field values with a given name.
     *
     * File fields are not returned by this method.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getValueArray(string $name): array
    {
        $values = [];

        foreach ($this->fields[$name] ?? [] as $field) {
            $values[] = $field;
        }

        return $values;
    }

    /**
     * Gets all field values.
     *
     * File fields are not returned by this method.
     *
     * @return string[][]
     */
    public function getValues(): array
    {
        return $this->fields;
    }

    /**
     * Checks whether at least one file field is available for the given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name][0]);
    }

    /**
     * Gets the first file field with a given name or null, if no such field exists.
     *
     * @param string $name
     *
     * @return File|null
     */
    public function getFile(string $name)
    {
        return $this->files[$name][0] ?? null;
    }

    /**
     * Gets all file fields with a given name.
     *
     * @param string $name
     *
     * @return File[]
     */
    public function getFileArray(string $name): array
    {
        return $this->files[$name] ?? [];
    }

    /**
     * Gets all files.
     *
     * @return File[][]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Returns the names of all fields.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return $this->names ?? $this->names = \array_map("strval", \array_keys($this->fields));
    }
}
