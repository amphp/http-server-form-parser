<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;
use Amp\Promise;

/**
 * Try parsing a the request's body with either x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 * @param int     $fieldLengthLimit
 * @param int     $fieldCountLimit
 *
 * @return Promise<Form>
 */
function parseForm(
    Request $request,
    int $fieldLengthLimit = BufferingParser::DEFAULT_FIELD_LENGTH_LIMIT,
    int $fieldCountLimit = BufferingParser::DEFAULT_FIELD_COUNT_LIMIT
): Promise {
    return (new BufferingParser($request, $fieldLengthLimit, $fieldCountLimit))->parseForm();
}
