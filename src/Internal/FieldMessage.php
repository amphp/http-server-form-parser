<?php

namespace Amp\Http\Server\FormParser\Internal;

use Amp\Http\Message;

/** @internal */
final class FieldMessage extends Message
{
    public function __construct(array $headers)
    {
        foreach ($headers as [$key, $value]) {
            $this->addHeader($key, $value);
        }
    }
}
