<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\FormParser\File;
use Amp\Http\Server\FormParser\Form;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase {
    public function testFormWithNumericFieldNames() {
        $form = new Form([
            12 => ["21"],
            "foo" => ["bar"],
        ]);

        $this->assertSame(["12", "foo"], $form->getNames());
    }
}
