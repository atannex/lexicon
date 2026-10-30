<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api\Http\Controllers;

use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Modules\Editorial\EditorialWorkflow;
use Atannex\Lexicon\Modules\Editorial\Enums\SubmissionKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Only routed when the editorial module is enabled. */
final class SubmissionsController
{
    public function store(Request $request, EditorialWorkflow $workflow): JsonResponse
    {
        $input = $request->validate([
            'language' => ['required', 'string', 'max:64'],
            'kind' => ['required', Rule::enum(SubmissionKind::class)],
            'entry' => ['nullable', 'string', 'max:26'],
            'payload' => ['required', 'array'],
            'source_id' => ['nullable', 'string', 'max:26'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        abort_if($user === null, 401);

        $language = Language::query()->where('code', $input['language'])->firstOrFail();
        $target = isset($input['entry']) ? Lexeme::query()->where('uid', $input['entry'])->where('language_id', $language->id)->firstOrFail() : null;

        $submission = $workflow->submit($user, $language, SubmissionKind::from($input['kind']), $input['payload'], $target, $input['source_id'] ?? null, $input['note'] ?? null);

        return new JsonResponse(['data' => ['uid' => $submission->uid, 'status' => $submission->status->value]], 201);
    }
}
