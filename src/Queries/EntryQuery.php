<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Queries;

use Atannex\Lexicon\Actions\ChangeLexemeStatus;
use Atannex\Lexicon\Actions\CreateLexeme;
use Atannex\Lexicon\Actions\DeleteLexeme;
use Atannex\Lexicon\Actions\UpdateLexeme;
use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\UpdateLexemeData;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The entries of one language. Reads default to what an anonymous reader
 * may see (published, public); widen them with for($user) or, in trusted
 * code, withAccess(ReadAccess::everything()).
 *
 * Writes are not authorised here: the PHP API trusts its caller, exactly
 * like Eloquent. HTTP and console entry points authorise first.
 */
final class EntryQuery
{
    private ReadAccess $access;

    /** @var list<string> */
    private array $with = ['lemma'];

    public function __construct(private readonly Language $language)
    {
        $this->access = ReadAccess::public();
    }

    public function for(?Authenticatable $user): self
    {
        return $this->withAccess(ReadAccess::for($user));
    }

    public function withAccess(ReadAccess $access): self
    {
        $clone = clone $this;
        $clone->access = $access;

        return $clone;
    }

    /** Eager-load more relations, e.g. with('senses.definitions', 'forms'). */
    public function with(string ...$relations): self
    {
        $clone = clone $this;
        $clone->with = array_values(array_unique([...$this->with, ...$relations]));

        return $clone;
    }

    /** @return Builder<Lexeme> in the language's alphabetical order */
    public function query(): Builder
    {
        return Lexeme::query()
            ->where('language_id', $this->language->id)
            ->readable($this->access->visibilities, publishedOnly: ! $this->access->unpublished)
            ->with($this->with)
            ->orderBy(Form::query()->select('sort_key')->whereColumn('id', (new Lexeme)->qualifyColumn('lemma_form_id')))
            ->orderBy((new Lexeme)->qualifyColumn('id'));
    }

    /** @return LengthAwarePaginator<int, Lexeme> */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()->paginate($this->bounded($perPage));
    }

    /**
     * Keyset pagination for large dictionaries and exports.
     *
     * @return CursorPaginator<int, Lexeme>
     */
    public function cursorPaginate(int $perPage = 20): CursorPaginator
    {
        return Lexeme::query()
            ->where('language_id', $this->language->id)
            ->readable($this->access->visibilities, publishedOnly: ! $this->access->unpublished)
            ->with($this->with)
            ->orderBy('id')
            ->cursorPaginate($this->bounded($perPage));
    }

    /** @return Collection<int, Lexeme> */
    public function get(int $limit = 100): Collection
    {
        return $this->query()->limit($this->bounded($limit))->get();
    }

    public function count(): int
    {
        return $this->query()->toBase()->getCountForPagination();
    }

    public function find(string $uid): ?Lexeme
    {
        return $this->query()->where('uid', $uid)->first();
    }

    public function create(CreateLexemeData $data): Lexeme
    {
        return app(CreateLexeme::class)->handle($this->language, $data);
    }

    public function update(Lexeme $lexeme, UpdateLexemeData $data, ?string $actor = null): Lexeme
    {
        return app(UpdateLexeme::class)->handle($this->own($lexeme), $data, $actor);
    }

    public function changeStatus(Lexeme $lexeme, EntryStatus $status, ?string $actor = null): Lexeme
    {
        return app(ChangeLexemeStatus::class)->handle($this->own($lexeme), $status, $actor);
    }

    public function publish(Lexeme $lexeme, ?string $actor = null): Lexeme
    {
        return $this->changeStatus($lexeme, EntryStatus::Published, $actor);
    }

    public function delete(Lexeme $lexeme, ?string $actor = null): void
    {
        app(DeleteLexeme::class)->handle($this->own($lexeme), $actor);
    }

    private function own(Lexeme $lexeme): Lexeme
    {
        if ($lexeme->language_id !== $this->language->id) {
            throw IntegrityViolation::make("The entry is not in language [{$this->language->code}].");
        }

        return $lexeme;
    }

    private function bounded(int $size): int
    {
        $max = config('lexicon.search.max_limit', 100);

        return max(1, min($size, is_int($max) ? $max : 100));
    }
}
