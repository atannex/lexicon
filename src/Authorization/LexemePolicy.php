<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Authorization;

use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Models\Lexeme;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Default policy for entries, built on the Abilities your application
 * grants. Register your own policy for Lexeme to replace it.
 */
class LexemePolicy
{
    public function view(?Authenticatable $user, Lexeme $lexeme): bool
    {
        $access = ReadAccess::for($user);

        return $access->allows($lexeme->visibility) && ($lexeme->status === EntryStatus::Published || $access->unpublished);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, Abilities::EDIT);
    }

    public function update(Authenticatable $user, Lexeme $lexeme): bool
    {
        return $this->allows($user, Abilities::EDIT) && ReadAccess::for($user)->allows($lexeme->visibility);
    }

    public function publish(Authenticatable $user, Lexeme $lexeme): bool
    {
        return $this->allows($user, Abilities::PUBLISH) && ReadAccess::for($user)->allows($lexeme->visibility);
    }

    public function delete(Authenticatable $user, Lexeme $lexeme): bool
    {
        return $this->allows($user, Abilities::DELETE) && ReadAccess::for($user)->allows($lexeme->visibility);
    }

    private function allows(Authenticatable $user, string $ability): bool
    {
        return app(Gate::class)->forUser($user)->allows($ability);
    }
}
