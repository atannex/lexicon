<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Models;

use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Models\Concerns\BelongsToTenant;
use Atannex\Lexicon\Models\Concerns\HasUid;
use Atannex\Lexicon\Models\Concerns\UsesLexiconTable;
use Illuminate\Database\Eloquent\Model;

/**
 * A person credited for lexicon work: a speaker, transcriber, reviewer.
 * user_id optionally links an account in the host application. Their name
 * is only shown publicly when credit_publicly is true.
 *
 * @property int $id
 * @property string $uid
 * @property string $tenant
 * @property string|null $user_id
 * @property string $display_name
 * @property string|null $credit_as
 * @property bool $credit_publicly
 * @property list<string>|null $roles
 * @property ConsentStatus $consent
 * @property array<string, mixed>|null $metadata
 */
class Contributor extends Model
{
    use BelongsToTenant;
    use HasUid;
    use UsesLexiconTable;

    protected $fillable = ['user_id', 'display_name', 'credit_as', 'credit_publicly', 'roles', 'consent', 'metadata'];

    protected function baseTable(): string
    {
        return 'contributors';
    }

    protected function casts(): array
    {
        return ['roles' => 'array', 'metadata' => 'array', 'credit_publicly' => 'boolean', 'consent' => ConsentStatus::class];
    }

    /** The name to print in a public credit line, or null to stay anonymous. */
    public function publicCredit(): ?string
    {
        return $this->credit_publicly ? ($this->credit_as ?? $this->display_name) : null;
    }
}
