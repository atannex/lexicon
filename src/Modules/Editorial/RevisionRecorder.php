<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial;

use Atannex\Lexicon\Events\LexemeCreated;
use Atannex\Lexicon\Events\LexemeDeleted;
use Atannex\Lexicon\Events\LexemeStatusChanged;
use Atannex\Lexicon\Events\LexemeUpdated;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Modules\Editorial\Models\Revision;
use Atannex\Lexicon\Support\EntrySnapshot;
use Atannex\Lexicon\Tenancy\TenantContext;

/**
 * Listens to the core's domain events and stores a snapshot of the entry
 * after every change. The core knows nothing about revisions.
 */
final readonly class RevisionRecorder
{
    public function __construct(private TenantContext $tenants) {}

    public function created(LexemeCreated $event): void
    {
        $this->record($event->lexeme, 'created', null);
    }

    public function updated(LexemeUpdated $event): void
    {
        $this->record($event->lexeme, $event->change, $event->actor);
    }

    public function statusChanged(LexemeStatusChanged $event): void
    {
        $this->record($event->lexeme, 'status:'.$event->to->value, $event->actor);
    }

    public function deleted(LexemeDeleted $event): void
    {
        $this->tenants->run($event->tenant, fn () => $this->store($event->uid, 'deleted', null, $event->actor));
    }

    private function record(Lexeme $lexeme, string $change, ?string $actor): void
    {
        $fresh = Lexeme::query()->withoutGlobalScopes()->with(EntrySnapshot::RELATIONS)->find($lexeme->id);

        if ($fresh !== null) {
            $this->tenants->run($fresh->tenant, fn () => $this->store($fresh->uid, $change, EntrySnapshot::of($fresh), $actor));
        }
    }

    /** @param array<string, mixed>|null $snapshot */
    private function store(string $uid, string $change, ?array $snapshot, ?string $actor): void
    {
        $version = (int) Revision::query()->where('lexeme_uid', $uid)->max('version') + 1;

        Revision::query()->create([
            'lexeme_uid' => $uid,
            'version' => $version,
            'change' => mb_substr($change, 0, 32),
            'snapshot' => $snapshot,
            'actor' => $actor,
        ]);
    }
}
