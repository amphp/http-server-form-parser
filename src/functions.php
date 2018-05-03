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
 * @return Promise<Form>
 */
function parseBody(Request $request, int $sizeLimit = FormParser::DEFAULT_MAX_BODY_SIZE): Promise {
    return (new FormParser($request, $sizeLimit))->parse();
}
