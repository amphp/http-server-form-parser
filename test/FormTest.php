<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\FormParser\Field;
use Amp\Http\Server\FormParser\Form;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase {
    public function testFormWithNumericFieldNames() {
        $form = new Form([
            12 => [new Field("12")],
            "foo" => [new Field("foo")],
        ]);

        $this->assertSame(["12", "foo"], $form->getNames());
    }
}
