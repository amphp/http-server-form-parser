<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;

/**
 * Try parsing a the request's body with either x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 * @param int     $sizeLmit Optional body size limit.
 *
 * @return BodyParser (returns a ParsedBody instance when yielded)
 */
function parseBody(Request $request, int $sizeLmit = BodyParser::DEFAULT_MAX_BODY_SIZE): BodyParser {
    return new BodyParser($request, $sizeLmit);
}