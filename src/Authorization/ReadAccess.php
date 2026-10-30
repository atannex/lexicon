<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Authorization;

use Atannex\Lexicon\Enums\Visibility;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a reader may see: which visibilities, and whether unpublished
 * entries are included. Built from the Gate for a user, or explicitly by
 * trusted code (commands, exports run by the system).
 */
final readonly class ReadAccess
{
    /** @param list<Visibility> $visibilities */
    public function __construct(public array $visibilities, public bool $unpublished = false) {}

    /** Anonymous readers: published, public records only. */
    public static function public(): self
    {
        return new self([Visibility::Public]);
    }

    /** Everything. For trusted system code only, never for a request. */
    public static function everything(): self
    {
        return new self(Visibility::cases(), unpublished: true);
    }

    public static function for(?Authenticatable $user): self
    {
        if ($user === null) {
            return self::public();
        }

        $gate = app(Gate::class)->forUser($user);

        $visibilities = array_values(array_filter(
            Visibility::cases(),
            static fn (Visibility $v): bool => $v->ability() === null || $gate->allows($v->ability()),
        ));

        return new self($visibilities, $gate->allows(Abilities::VIEW_UNPUBLISHED));
    }

    public function allows(Visibility $visibility): bool
    {
        return in_array($visibility, $this->visibilities, true);
    }
}
