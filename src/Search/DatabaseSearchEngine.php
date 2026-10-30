<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Search;

use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Contracts\SearchEngine;
use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Enums\MatchMode;
use Atannex\Lexicon\Enums\SearchField;
use Atannex\Lexicon\Models\Definition;
use Atannex\Lexicon\Models\Example;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Atannex\Lexicon\Support\Unicode;
use Atannex\Lexicon\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Portable search on the lexicon's own tables, for SQLite, MySQL and
 * PostgreSQL alike. It compares precomputed keys, so no database collation
 * or extension decides what counts as the same spelling.
 *
 * Ranking: whole-form matches beat prefix matches beat substring matches,
 * the lemma beats other forms, forms beat definitions beat examples. Ties
 * are ordered by the lemma's sort key (the orthography's alphabet order).
 */
final readonly class DatabaseSearchEngine implements SearchEngine
{
    private const array SCORES = [
        'whole' => 100, 'prefix' => 70, 'contains' => 40,
        'definition' => 20, 'example' => 10, 'lemma_bonus' => 5,
    ];

    public function __construct(private OrthographyRegistry $orthographies, private TenantContext $tenants) {}

    public function search(SearchQuery $query): SearchResults
    {
        if (trim($query->term) === '') {
            return new SearchResults([], $query);
        }

        Unicode::assertValid($query->term);

        $candidates = max($query->limit * 3, 30);

        /** @var array<int, array{score: int, field: SearchField, matched: string}> $best */
        $best = [];

        $consider = static function (int $lexemeId, int $score, SearchField $field, string $matched) use (&$best): void {
            if (! isset($best[$lexemeId]) || $best[$lexemeId]['score'] < $score) {
                $best[$lexemeId] = ['score' => $score, 'field' => $field, 'matched' => $matched];
            }
        };

        $profiles = $query->languageId === null
            ? [$this->orthographies->default()]
            : $this->orthographies->profilesForLanguage($query->languageId);

        $keys = $this->keys($query, $profiles);

        if ($keys === []) {
            return new SearchResults([], $query);
        }

        if (in_array(SearchField::Forms, $query->fields, true)) {
            foreach ($this->forms($query, $keys, $candidates) as $form) {
                $key = (string) $form->getAttribute($query->sensitivity->column());
                $score = match (true) {
                    in_array($key, $keys, true) => self::SCORES['whole'],
                    $this->startsWithAny($key, $keys) => self::SCORES['prefix'],
                    default => self::SCORES['contains'],
                } + ($form->kind === FormKind::Lemma ? self::SCORES['lemma_bonus'] : 0);

                $consider($form->lexeme_id, $score, SearchField::Forms, $form->text);
            }
        }

        if (in_array(SearchField::Definitions, $query->fields, true)) {
            $definitionKeys = $this->keys($query, [$this->orthographies->default()]) ?: $keys;
            $definitions = $this->text(Definition::query(), $query, $definitionKeys)
                ->when($query->definitionLanguage, fn (Builder $q, string $tag) => $q->where('written_in', $tag))
                ->limit($candidates)->get();

            foreach ($definitions as $definition) {
                $consider($definition->lexeme_id, self::SCORES['definition'], SearchField::Definitions, $definition->text);
            }
        }

        if (in_array(SearchField::Examples, $query->fields, true)) {
            foreach ($this->text(Example::query(), $query, $keys)->limit($candidates)->get() as $example) {
                $consider($example->lexeme_id, self::SCORES['example'], SearchField::Examples, $example->text);
            }
        }

        return new SearchResults($this->rank($best, $query), $query);
    }

    /**
     * The term keyed by every profile the language uses, so a form keyed
     * under its own orthography is still found.
     *
     * @param  list<OrthographyProfile>  $profiles
     * @return list<string>
     */
    private function keys(SearchQuery $query, array $profiles): array
    {
        $keys = array_map(static fn (OrthographyProfile $p): string => mb_substr($p->searchKey($query->term, $query->sensitivity), 0, Form::KEY_LENGTH), $profiles);

        return array_values(array_unique(array_filter($keys, static fn (string $k): bool => $k !== '')));
    }

    /**
     * Whole matches first, then (for prefix and contains) partial ones, so
     * the candidate limit never cuts off an exact hit.
     *
     * @param  list<string>  $keys
     * @return list<Form>
     */
    private function forms(SearchQuery $query, array $keys, int $limit): array
    {
        $column = $query->sensitivity->column();
        $whole = $this->formQuery($query)->whereIn($column, $keys)->orderBy('sort_key')->limit($limit)->get();

        if ($query->mode === MatchMode::Whole || $whole->count() >= $limit) {
            return array_values($whole->all());
        }

        $partial = $this->formQuery($query)
            ->whereNotIn('id', $whole->modelKeys())
            ->where(function (Builder $q) use ($query, $keys): void {
                foreach ($keys as $key) {
                    $pattern = ($query->mode === MatchMode::Contains ? '%' : '').Unicode::escapeLike($key).'%';
                    $q->orWhereRaw(self::like($query->sensitivity->column()), [$pattern]);
                }
            })
            ->orderByRaw(self::length($column))
            ->orderBy('sort_key')
            ->limit($limit - $whole->count())
            ->get();

        return [...array_values($whole->all()), ...array_values($partial->all())];
    }

    /** @return Builder<Form> */
    private function formQuery(SearchQuery $query): Builder
    {
        return Form::query()
            ->where('tenant', $this->tenants->current())
            ->when($query->languageId, fn (Builder $q, int $id) => $q->where('language_id', $id))
            ->when($query->varietyId, fn (Builder $q, int $id) => $q->where(fn (Builder $q) => $q->where('variety_id', $id)->orWhereNull('variety_id')))
            ->whereIn('lexeme_id', $this->readable($query))
            ->select(['id', 'lexeme_id', 'kind', 'text', 'sort_key', $query->sensitivity->column()]);
    }

    /**
     * LIKE with an explicit escape character: SQLite has no default one,
     * and '!' avoids backslash handling differences between MySQL and
     * PostgreSQL. Columns are fixed names, never user input.
     *
     * @return literal-string
     */
    private static function like(string $column): string
    {
        return match ($column) {
            'key_exact' => "key_exact like ? escape '!'",
            'key_toneless' => "key_toneless like ? escape '!'",
            'key_bare' => "key_bare like ? escape '!'",
            'search_exact' => "search_exact like ? escape '!'",
            'search_toneless' => "search_toneless like ? escape '!'",
            default => "search_bare like ? escape '!'",
        };
    }

    /** @return literal-string */
    private static function length(string $column): string
    {
        return match ($column) {
            'key_exact' => 'length(key_exact)',
            'key_toneless' => 'length(key_toneless)',
            default => 'length(key_bare)',
        };
    }

    /**
     * Running text is always matched as a substring.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $builder
     * @param  list<string>  $keys
     * @return Builder<T>
     */
    private function text(Builder $builder, SearchQuery $query, array $keys): Builder
    {
        $like = self::like($query->sensitivity->textColumn());

        return $builder
            ->whereIn('lexeme_id', $this->readable($query))
            ->where(function (Builder $q) use ($keys, $like): void {
                foreach ($keys as $key) {
                    $q->orWhereRaw($like, ['%'.Unicode::escapeLike($key).'%']);
                }
            })
            ->select(['id', 'lexeme_id', 'text']);
    }

    /** @return Builder<Lexeme> ids of entries the reader may see */
    private function readable(SearchQuery $query): Builder
    {
        return Lexeme::query()
            ->readable($query->access->visibilities, publishedOnly: ! $query->access->unpublished)
            ->when($query->languageId, fn (Builder $q, int $id) => $q->where('language_id', $id))
            ->when($query->partOfSpeech, fn (Builder $q, string $pos) => $q->where('part_of_speech', $pos))
            ->select('id');
    }

    /** @param list<string> $keys */
    private function startsWithAny(string $value, array $keys): bool
    {
        foreach ($keys as $key) {
            if (str_starts_with($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{score: int, field: SearchField, matched: string}>  $best
     * @return list<SearchHit>
     */
    private function rank(array $best, SearchQuery $query): array
    {
        if ($best === []) {
            return [];
        }

        $lexemes = Lexeme::query()->whereKey(array_keys($best))->with(['lemma', 'language'])->get()->keyBy('id');

        $hits = [];

        foreach ($best as $id => $match) {
            if ($lexeme = $lexemes->get($id)) {
                $hits[] = new SearchHit($lexeme, $match['field'], $match['matched'], $match['score']);
            }
        }

        usort($hits, static fn (SearchHit $a, SearchHit $b): int => [$b->score, $a->lexeme->lemma->sort_key ?? '', $a->lexeme->id] <=> [$a->score, $b->lexeme->lemma->sort_key ?? '', $b->lexeme->id]);

        return array_slice($hits, 0, $query->limit);
    }
}
