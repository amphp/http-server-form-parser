<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;

/**
 * Try parsing a the request's body with either x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 * @param int     $sizeLimit Optional body size limit.
 *
 * @return BodyParser (returns a ParsedBody instance when yielded)
 */
function parseBody(Request $request, int $sizeLimit = BodyParser::DEFAULT_MAX_BODY_SIZE): BodyParser {
    return new BodyParser($request, $sizeLimit);
}
