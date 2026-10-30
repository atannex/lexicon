<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial;

use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Modules\Editorial\Models\Revision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class RevisionHistory
{
    /** @return Collection<int, Revision> oldest first */
    public function of(Lexeme|string $lexeme): Collection
    {
        return Revision::query()->where('lexeme_uid', $lexeme instanceof Lexeme ? $lexeme->uid : $lexeme)->orderBy('version')->get();
    }

    /**
     * What changed between two revisions, as dot paths:
     * ['senses.0.definitions.1.text' => ['old', 'new'], ...]. A path missing
     * on one side shows null there.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(Revision $from, Revision $to): array
    {
        $old = Arr::dot($from->snapshot ?? []);
        $new = Arr::dot($to->snapshot ?? []);
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $path) {
            $before = $old[$path] ?? null;
            $after = $new[$path] ?? null;

            if ($before !== $after) {
                $changes[(string) $path] = [$before, $after];
            }
        }

        ksort($changes);

        return $changes;
    }
}
