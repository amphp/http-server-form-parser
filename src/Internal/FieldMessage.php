<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser\Internal;

use Amp\Http\HttpMessage;

/** @internal */
final class FieldMessage extends HttpMessage
{
    public function __construct(array $headers)
    {
        foreach ($headers as [$key, $value]) {
            $this->addHeader($key, $value);
        }
    }
}
