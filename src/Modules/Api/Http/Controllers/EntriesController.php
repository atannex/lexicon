<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api\Http\Controllers;

use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\UpdateLexemeData;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Modules\Api\Http\Resources\EntryResource;
use Atannex\Lexicon\Support\EntrySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Entries are addressed by uid. An entry the reader may not see answers
 * 404, exactly like one that does not exist, so its existence never leaks.
 */
final class EntriesController
{
    public function index(Request $request, string $language): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', (int) config('lexicon.api.per_page', 20));

        $page = Lexicon::language($this->language($language))->entries()->for($request->user())
            ->with(...EntrySnapshot::RELATIONS)
            ->paginate($perPage);

        return EntryResource::collection($page);
    }

    public function show(Request $request, string $entry): EntryResource
    {
        return new EntryResource($this->visible($request, $entry)->load(['attestations.source', 'attestations.contributor']));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Lexeme::class);

        $language = $this->language((string) $request->string('language'));
        $entry = Lexicon::language($language)->entries()->create(CreateLexemeData::fromArray($request->except('language')));

        return (new EntryResource($entry))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $entry): EntryResource
    {
        $lexeme = $this->visible($request, $entry);
        Gate::authorize('update', $lexeme);

        $updated = Lexicon::language($lexeme->language)->entries()->update($lexeme, UpdateLexemeData::fromArray($request->all()), self::actor($request));

        return new EntryResource($updated);
    }

    public function destroy(Request $request, string $entry): JsonResponse
    {
        $lexeme = $this->visible($request, $entry);
        Gate::authorize('delete', $lexeme);

        Lexicon::language($lexeme->language)->entries()->delete($lexeme, self::actor($request));

        return new JsonResponse(null, 204);
    }

    private function visible(Request $request, string $uid): Lexeme
    {
        $lexeme = Lexeme::query()->where('uid', $uid)->firstOrFail();

        abort_unless(Gate::forUser($request->user())->allows('view', $lexeme), 404);

        return $lexeme;
    }

    private function language(string $code): Language
    {
        return Language::query()->where('code', $code)->where('is_active', true)->firstOrFail();
    }

    private static function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : null;
    }
}
