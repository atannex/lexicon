<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api\Http\Controllers;

use Atannex\Lexicon\Enums\MatchMode;
use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Enums\SearchField;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Modules\Api\Http\Resources\EntryResource;
use Atannex\Lexicon\Search\SearchHit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'language' => ['nullable', 'string', 'max:64'],
            'variety' => ['nullable', 'string', 'max:64'],
            'sensitivity' => ['nullable', Rule::enum(MatchSensitivity::class)],
            'mode' => ['nullable', Rule::enum(MatchMode::class)],
            'fields' => ['nullable', 'array'],
            'fields.*' => [Rule::enum(SearchField::class)],
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = isset($input['language'])
            ? Lexicon::language(Language::query()->where('code', $input['language'])->firstOrFail())->search($input['q'])
            : Lexicon::search($input['q']);

        $search->for($request->user());

        if (isset($input['variety'])) {
            $search->variety($input['variety']);
        }

        if (isset($input['sensitivity'])) {
            $search->sensitivity(MatchSensitivity::from($input['sensitivity']));
        }

        if (isset($input['mode'])) {
            $search->mode(MatchMode::from($input['mode']));
        }

        if (isset($input['fields'])) {
            $search->in(...array_map(SearchField::from(...), $input['fields']));
        }

        if (isset($input['limit'])) {
            $search->limit((int) $input['limit']);
        }

        $hits = array_map(fn (SearchHit $hit): array => [
            'score' => $hit->score,
            'field' => $hit->field->value,
            'matched' => $hit->matched,
            'entry' => (new EntryResource($hit->lexeme))->toArray($request),
        ], $search->get()->hits);

        return new JsonResponse(['data' => $hits], options: JSON_UNESCAPED_UNICODE);
    }
}
