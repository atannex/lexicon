<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api\Http\Resources;

use Atannex\Lexicon\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Language */
final class LanguageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'iso639_3' => $this->iso639_3,
            'documentation_status' => $this->documentation_status->value,
            'description' => $this->description,
            'geography' => $this->geography,
            'alternative_names' => $this->whenLoaded('names', fn () => $this->names->map(fn ($n) => ['name' => $n->name, 'kind' => $n->kind, 'written_in' => $n->written_in])->all()),
            'varieties' => $this->whenLoaded('varieties', fn () => $this->varieties->map(fn ($v) => ['code' => $v->code, 'name' => $v->name, 'native_name' => $v->native_name, 'kind' => $v->kind->value])->all()),
            'orthographies' => $this->whenLoaded('orthographies', fn () => $this->orthographies->map(fn ($o) => ['code' => $o->code, 'name' => $o->name, 'kind' => $o->kind->value, 'script' => $o->script, 'direction' => $o->direction, 'default' => $o->is_default])->all()),
        ];
    }
}
