<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atannex\Lexicon\Queries\LanguageRegistry languages()
 * @method static \Atannex\Lexicon\Queries\LanguageScope language(string|\Atannex\Lexicon\Models\Language $language)
 * @method static \Atannex\Lexicon\Search\SearchBuilder search(string $term)
 * @method static \Atannex\Lexicon\Models\Source createSource(\Atannex\Lexicon\Data\CreateSourceData $data)
 * @method static \Atannex\Lexicon\Models\Source|null findSource(string $uid)
 * @method static \Atannex\Lexicon\Models\Contributor createContributor(\Atannex\Lexicon\Data\ContributorData $data)
 * @method static mixed forTenant(string|int|null $tenant, \Closure $callback)
 * @method static string tenant()
 * @method static \Atannex\Lexicon\Orthography\OrthographyRegistry orthographies()
 * @method static \Atannex\Lexicon\Extensions\ExtensionRegistry extensions()
 * @method static bool supports(string $capability)
 *
 * @see \Atannex\Lexicon\Lexicon
 */
final class Lexicon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atannex\Lexicon\Lexicon::class;
    }
}
