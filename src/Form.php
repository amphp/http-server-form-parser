<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

final class Form
{
    /** @var list<string>|null */
    private ?array $names = null;

    /**
     * @param array<string, list<string>> $fields
     * @param array<string, list<BufferedFile>> $files
     */
    public function __construct(
        private readonly array $fields,
        private readonly array $files = [],
    ) {
    }

    /**
     * Gets the first field value with a given name or null, if no such field exists.
     *
     * File fields are not returned by this method.
     */
    public function getValue(string $name): ?string
    {
        return $this->fields[$name][0] ?? null;
    }

    /**
     * Gets all field values with a given name.
     *
     * File fields are not returned by this method.
     *
     * @return list<string>
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
     * @return array<string, list<string>>
     */
    public function getValues(): array
    {
        return $this->fields;
    }

    /**
     * Checks whether at least one file field is available for the given name.
     */
    public function hasFile(string $name): bool
    {
        return isset($this->files[$name][0]);
    }

    /**
     * Gets the first file field with a given name or null, if no such field exists.
     */
    public function getFile(string $name): ?BufferedFile
    {
        return $this->files[$name][0] ?? null;
    }

    /**
     * Gets all file fields with a given name.
     *
     * @return list<BufferedFile>
     */
    public function getFileArray(string $name): array
    {
        return $this->files[$name] ?? [];
    }

    /**
     * Gets all files.
     *
     * @return array<string, list<BufferedFile>>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Returns the names of all fields.
     *
     * @return list<string>
     */
    public function getNames(): array
    {
        return $this->names ??= \array_map(strval(...), \array_keys($this->fields));
    }
}
