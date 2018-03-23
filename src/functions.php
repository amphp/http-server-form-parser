<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;
use Amp\Promise;

/**
 * Try parsing a the request's body with either x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 * @param int     $sizeLimit Optional body size limit.
 *
 * @return Promise<ParsedBody>
 */
function parseBody(Request $request, int $sizeLimit = BodyParser::DEFAULT_MAX_BODY_SIZE): Promise {
    return (new BodyParser($request, $sizeLimit))->parse();
}
