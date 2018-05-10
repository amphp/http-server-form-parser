<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;
use Amp\Promise;

/**
 * Try parsing a the request's body with either application/x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 *
 * @return Promise<Form>
 */
function parseForm(Request $request): Promise {
    static $parser;

    if ($parser === null) {
        $parser = new BufferingParser;
    }

    return $parser->parseForm($request);
}
