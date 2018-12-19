<?php

namespace Amp\Http\Server\FormParser\Test;

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
        $form = new Form([
            12 => ["12"],
        ], [
            "file" => ["file_path"],
        ]);

        $this->assertSame("file_path", $form->getFile("file"));
        $this->assertSame(["file_path"], $form->getFileArray("file"));
        $this->assertNull($form->getFile("file_not_found"));
        $this->assertTrue($form->hasFile("file"));
    }
}
