<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Enums\Certainty;
use Atannex\Lexicon\Enums\LanguageRelationshipType;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\LanguageRelationship;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Tenancy\TenantContext;

/**
 * Records that two identities are related. Nothing is merged: Nweh and a
 * neighbouring variety stay separate records however they are related.
 */
final class RelateLanguages extends Action
{
    public function __construct(private readonly TenantContext $tenants, private readonly References $references) {}

    public function handle(
        Language $from,
        LanguageRelationshipType $type,
        Language $to,
        Certainty $certainty = Certainty::Proposed,
        int|string|null $sourceId = null,
        ?string $notes = null,
    ): LanguageRelationship {
        $this->references->assertVisible($from);
        $this->references->assertVisible($to);

        if ($from->is($to)) {
            throw IntegrityViolation::make('A language cannot be related to itself.');
        }

        $key = [
            'tenant' => $this->tenants->current(),
            'from_language_id' => $from->id,
            'to_language_id' => $to->id,
            'type' => $type->value,
        ];

        $relationship = LanguageRelationship::query()->where($key)->first() ?? (new LanguageRelationship)->forceFill($key);

        $relationship->fill([
            'certainty' => $certainty,
            'source_id' => $this->references->source($sourceId)?->id,
            'notes' => $notes,
        ])->save();

        return $relationship;
    }
}
