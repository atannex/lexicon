<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\ContributorData;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Models\Contributor;
use Atannex\Lexicon\Support\Text;

final class CreateContributor extends Action
{
    public function handle(ContributorData $data): Contributor
    {
        $userId = $data->userId === null ? null : (string) $data->userId;

        if ($userId !== null && Contributor::query()->where('user_id', $userId)->exists()) {
            throw IntegrityViolation::make("User [{$userId}] already has a contributor profile.");
        }

        return Contributor::query()->create([
            'user_id' => $userId,
            'display_name' => Text::require($data->displayName, 'name', 500),
            'credit_as' => Text::optional($data->creditAs, 'credit', 500),
            'credit_publicly' => $data->creditPublicly,
            'roles' => $data->roles ?: null,
            'consent' => $data->consent,
            'metadata' => $data->metadata ?: null,
        ]);
    }
}
