<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Api\Http\Resources;

use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Models\Attestation;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\EntrySnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An entry as the snapshot format, plus its sources. Source details are
 * only included when the reader may see the source's own visibility, and
 * contributors appear only under the name they chose to be credited by.
 *
 * @mixin Lexeme
 */
final class EntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = EntrySnapshot::of($this->resource);
        $access = ReadAccess::for($request->user());

        if (! $access->unpublished) {
            unset($entry['status']);
        }

        unset($entry['metadata']);

        $entry['published_at'] = $this->published_at?->toIso8601String();
        $entry['sources'] = $this->whenLoaded('attestations', fn () => $this->attestations
            ->filter(fn (Attestation $a): bool => $access->allows($a->source->visibility))
            ->map(fn (Attestation $a): array => [
                'title' => $a->source->title,
                'kind' => $a->source->kind->value,
                'citation' => $a->source->citation,
                'license' => $a->source->license,
                'locator' => $a->locator,
                'credit' => $a->contributor?->publicCredit(),
            ])->values()->all());

        return $entry;
    }
}
