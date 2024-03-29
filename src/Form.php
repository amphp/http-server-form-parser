<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Server\Request;

final class Form
{
    use ForbidCloning;
    use ForbidSerialization;

    private static FormParser $formParser;

    /**
     * Try parsing the request's body with either application/x-www-form-urlencoded or multipart/form-data.
     */
    public static function fromRequest(Request $request, ?FormParser $formParser = null): self
    {
        if ($request->hasAttribute(Form::class)) {
            return $request->getAttribute(Form::class);
        }

        $form = ($formParser ?? self::getFormParser())->parseForm($request);

        $request->setAttribute(Form::class, $form);

        return $form;
    }

    private static function getFormParser(): FormParser
    {
        self::$formParser ??= new FormParser();

        return self::$formParser;
    }

    /** @var list<non-empty-string>|null */
    private ?array $names = null;

    /**
     * @param array<non-empty-string, list<string>> $fields
     * @param array<non-empty-string, list<BufferedFile>> $files
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
     * @return array<non-empty-string, list<string>>
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
        /** @psalm-suppress PropertyTypeCoercion */
        return $this->names ??= \array_map(\strval(...), \array_keys($this->fields));
    }
}
