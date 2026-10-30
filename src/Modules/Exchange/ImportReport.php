<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange;

final class ImportReport
{
    public int $created = 0;

    /** @var array<int, string> record number => why it was skipped */
    public array $skipped = [];

    /** @var array<int, list<string>> record number => errors */
    public array $failed = [];

    public function __construct(public readonly bool $dryRun) {}

    public function ok(): bool
    {
        return $this->failed === [];
    }
}
