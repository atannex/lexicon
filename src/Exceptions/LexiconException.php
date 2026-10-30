<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base class: catch this to handle every lexicon failure. JSON requests
 * that hit one get a 422 with the rule's message; other requests fall
 * through to the application's exception handler.
 */
abstract class LexiconException extends RuntimeException
{
    public function render(Request $request): ?JsonResponse
    {
        return $request->expectsJson() ? new JsonResponse(['message' => $this->getMessage()], 422) : null;
    }
}
