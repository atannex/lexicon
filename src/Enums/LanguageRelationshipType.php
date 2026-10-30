<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/**
 * Asserted relationships between two language identities. Each assertion
 * carries a certainty and a source; nothing is inferred from names.
 */
enum LanguageRelationshipType: string
{
    case GeneticallyRelated = 'genetically_related';
    case MutuallyIntelligible = 'mutually_intelligible';
    case DescendedFrom = 'descended_from';
    case SameClusterAs = 'same_cluster_as';
    case ContactInfluence = 'contact_influence';
    case AlternativeIdentityOf = 'alternative_identity_of';

    public function isSymmetric(): bool
    {
        return match ($this) {
            self::GeneticallyRelated, self::MutuallyIntelligible, self::SameClusterAs => true,
            default => false,
        };
    }
}
