<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Editorial\Enums;

use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Data\DefinitionData;
use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Data\SenseData;
use Atannex\Lexicon\Data\UpdateLexemeData;

/** What a contributor proposes. The payload is validated by the matching Data class. */
enum SubmissionKind: string
{
    case NewEntry = 'new_entry';
    /** An alternative, historical or dialect spelling, or a transcription. */
    case AddForm = 'add_form';
    case AddSense = 'add_sense';
    /** payload: DefinitionData fields plus sense_id. */
    case AddDefinition = 'add_definition';
    /** A correction to the entry's own fields. */
    case Correction = 'correction';

    /** @return class-string */
    public function dataClass(): string
    {
        return match ($this) {
            self::NewEntry => CreateLexemeData::class,
            self::AddForm => FormData::class,
            self::AddSense => SenseData::class,
            self::AddDefinition => DefinitionData::class,
            self::Correction => UpdateLexemeData::class,
        };
    }

    public function needsTarget(): bool
    {
        return $this !== self::NewEntry;
    }
}
