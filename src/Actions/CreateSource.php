<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Actions;

use Atannex\Lexicon\Data\CreateSourceData;
use Atannex\Lexicon\Events\SourceCreated;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Support\References;
use Atannex\Lexicon\Support\Text;
use Illuminate\Contracts\Events\Dispatcher;

final class CreateSource extends Action
{
    public function __construct(private readonly References $references, private readonly Dispatcher $events) {}

    public function handle(CreateSourceData $data): Source
    {
        $source = Source::query()->create([
            'kind' => $data->kind,
            'title' => Text::require($data->title, 'title', 1000),
            'citation' => Text::optional($data->citation, 'citation'),
            'authors' => $data->authors ?: null,
            'publisher' => $data->publisher,
            'published' => $data->published,
            'url' => $data->url,
            'collected_on' => $data->collectedOn,
            'location' => $data->location,
            'license' => $data->license,
            'consent' => $data->consent,
            'visibility' => $data->visibility,
            'contributor_id' => $this->references->contributor($data->contributorId)?->id,
            'notes' => $data->notes,
            'metadata' => $data->metadata ?: null,
        ]);

        $this->events->dispatch(new SourceCreated($source));

        return $source;
    }
}
