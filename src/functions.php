<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;

/**
 * Try parsing the request's body with either application/x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 *
 * @return Form
 */
function parseForm(Request $request): Form
{
    static $parser;

    if ($parser === null) {
        $parser = new BufferingParser;
    }

    return $parser->parseForm($request);
}
