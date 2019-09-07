<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\FormParser\File;
use Amp\Http\Server\FormParser\Form;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase
{
    public function testFormWithNumericFieldNames()
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

    public function testFormWithFiles()
    {
        $file = new File("file_path", "contents");

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
