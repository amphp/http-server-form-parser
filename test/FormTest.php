<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\FormParser\BufferedFile;
use Amp\Http\Server\FormParser\Form;
use Amp\PHPUnit\AsyncTestCase;

class FormTest extends AsyncTestCase
{
    public function testFormWithNumericFieldNames(): void
    {
        $form = new Form([
            12 => ["21"],
            "foo" => ["bar"],
        ]);

        $this->assertSame(["12", "foo"], $form->getNames());
        $this->assertSame("bar", $form->getValue("foo"));
        $this->assertSame(["bar"], $form->getValueArray("foo"));
        $this->assertNull($form->getValue("not_found_key"));
        $this->assertSame([
            12 => ["21"],
            "foo" => ["bar"],
        ], $form->getValues());
    }

    public function testFormWithFiles(): void
    {
        $file = new BufferedFile("file_path", "contents");

        $form = new Form([
            12 => ["12"],
        ], [
            "file" => [$file],
        ]);

        $this->assertSame(["file" => [$file]], $form->getFiles());
        $this->assertSame($file, $form->getFile("file"));
        $this->assertSame([$file], $form->getFileArray("file"));
        $this->assertNull($form->getFile("file_not_found"));
        $this->assertTrue($form->hasFile("file"));
    }
}
