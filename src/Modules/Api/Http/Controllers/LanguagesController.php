<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api\Http\Controllers;

use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Modules\Api\Http\Resources\LanguageResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LanguagesController
{
    public function index(): AnonymousResourceCollection
    {
        return LanguageResource::collection(Language::query()->where('is_active', true)->orderBy('code')->get());
    }

    public function show(string $language): LanguageResource
    {
        $model = Language::query()->where('code', $language)->where('is_active', true)->with(['names', 'varieties', 'orthographies'])->firstOrFail();

        return new LanguageResource($model);
    }
}
