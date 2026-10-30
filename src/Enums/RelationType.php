<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

/**
 * Lexical relations. Each row is a directed assertion "from → type → to";
 * symmetric types read the same from both ends, directed ones invert.
 */
enum RelationType: string
{
    case Synonym = 'synonym';
    case Antonym = 'antonym';
    case Homonym = 'homonym';
    case Related = 'related';
    case VariantOf = 'variant_of';
    case HasVariant = 'has_variant';
    case DerivedFrom = 'derived_from';
    case DerivesTo = 'derives_to';
    case Broader = 'broader';
    case Narrower = 'narrower';
    case CrossLanguageEquivalent = 'cross_language_equivalent';

    public function inverse(): self
    {
        return match ($this) {
            self::VariantOf => self::HasVariant,
            self::HasVariant => self::VariantOf,
            self::DerivedFrom => self::DerivesTo,
            self::DerivesTo => self::DerivedFrom,
            self::Broader => self::Narrower,
            self::Narrower => self::Broader,
            default => $this,
        };
    }

    public function isSymmetric(): bool
    {
        return $this->inverse() === $this;
    }
}
