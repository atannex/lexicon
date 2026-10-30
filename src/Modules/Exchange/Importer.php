<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange;

use Atannex\Lexicon\Actions\CreateLexeme;
use Atannex\Lexicon\Data\CreateLexemeData;
use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Exceptions\LexiconException;
use Atannex\Lexicon\Models\Form;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Source;
use Atannex\Lexicon\Orthography\OrthographyRegistry;
use Atannex\Lexicon\Support\References;
use Generator;
use Illuminate\Validation\ValidationException;
use JsonException;
use SplFileObject;

/**
 * Imports entries as new drafts. It never overwrites or publishes:
 * verified records are only ever changed by people.
 *
 *  - every record is validated by CreateLexemeData before anything is written;
 *  - dry runs validate and detect duplicates without writing;
 *  - an entry whose lemma, homograph and part of speech already exist is
 *    skipped as a duplicate, and so is a record whose uid was imported
 *    before, so re-running a failed import is safe;
 *  - each record is its own transaction: one bad row never rolls back or
 *    blocks the others;
 *  - an optional source attests every imported lemma, and the record's
 *    original uid is kept in metadata.imported_uid.
 */
final class Importer
{
    public const int MAX_FILE_BYTES = 200 * 1024 * 1024;

    public function __construct(
        private readonly CreateLexeme $create,
        private readonly References $references,
        private readonly OrthographyRegistry $orthographies,
    ) {}

    public function import(Language $language, string $path, Format $format, bool $dryRun = false, ?Source $source = null): ImportReport
    {
        $this->references->assertVisible($language);

        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read [{$path}].");
        }

        if (filesize($path) > self::MAX_FILE_BYTES) {
            throw new \InvalidArgumentException('The file is larger than '.(self::MAX_FILE_BYTES / 1024 / 1024).' MB; split it into several JSON Lines files.');
        }

        if ($source !== null) {
            $this->references->assertOwned($source);
        }

        $report = new ImportReport($dryRun);
        $seen = [];

        foreach ($this->records($path, $format) as $number => $record) {
            if (is_string($record)) {
                $report->failed[$number] = [$record];

                continue;
            }

            try {
                $data = CreateLexemeData::fromArray($this->normalise($record, $source));
            } catch (ValidationException $e) {
                $report->failed[$number] = array_values(array_map('strval', array_merge(...array_values($e->errors()))));

                continue;
            }

            $importedUid = is_string($record['uid'] ?? null) ? $record['uid'] : null;
            $key = $this->duplicateKey($language, $data);

            if ($reason = $this->duplicate($language, $data, $importedUid, $key, $seen)) {
                $report->skipped[$number] = $reason;

                continue;
            }

            $seen[$key] = true;

            if ($dryRun) {
                $report->created++;

                continue;
            }

            try {
                $this->create->handle($language, $data);
                $report->created++;
            } catch (LexiconException $e) {
                $report->failed[$number] = [$e->getMessage()];
            }
        }

        return $report;
    }

    /**
     * Snapshot-shaped records (the export format) or flat CSV rows become
     * CreateLexemeData input. Status is always draft.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalise(array $record, ?Source $source): array
    {
        $forms = array_values(array_filter(
            is_array($record['forms'] ?? null) ? $record['forms'] : [],
            static fn (mixed $f): bool => is_array($f) && ($f['kind'] ?? null) !== 'lemma',
        ));

        $senses = is_array($record['senses'] ?? null) ? $record['senses'] : [];

        if ($senses === [] && isset($record['definition']) && $record['definition'] !== '') {
            $senses = [['definitions' => [['text' => $record['definition'], 'written_in' => $record['definition_language'] ?? $record['written_in'] ?? '']]]];
        }

        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];

        if (is_string($record['uid'] ?? null)) {
            $metadata['imported_uid'] = $record['uid'];
        }

        return array_filter([
            'lemma' => $record['lemma'] ?? null,
            'part_of_speech' => ($record['part_of_speech'] ?? '') === '' ? null : $record['part_of_speech'],
            'homograph' => ($record['homograph'] ?? '') === '' ? null : $record['homograph'],
            'variety' => ($record['variety'] ?? '') === '' ? null : $record['variety'],
            'visibility' => ($record['visibility'] ?? '') === '' ? null : $record['visibility'],
            'forms' => $forms,
            'senses' => $senses,
            'metadata' => $metadata,
            'source_id' => $source?->id,
            'status' => 'draft',
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }

    private function duplicateKey(Language $language, CreateLexemeData $data): string
    {
        $key = $this->orthographies->forOrthographyId(null, $language->id)->searchKey($data->lemma, MatchSensitivity::Exact);

        return mb_substr($key, 0, Form::KEY_LENGTH).'|'.($data->homograph ?? '').'|'.($data->partOfSpeech ?? '');
    }

    /** @param array<string, true> $seen */
    private function duplicate(Language $language, CreateLexemeData $data, ?string $importedUid, string $key, array $seen): ?string
    {
        if (isset($seen[$key])) {
            return 'duplicate of an earlier record in this file';
        }

        $existing = Lexeme::query()->where('language_id', $language->id);

        if ($importedUid !== null && (clone $existing)->where('metadata->imported_uid', $importedUid)->exists()) {
            return "already imported (uid {$importedUid})";
        }

        [$lemmaKey] = explode('|', $key);

        $clash = (clone $existing)
            ->where('homograph', $data->homograph)
            ->where('part_of_speech', $data->partOfSpeech)
            ->whereHas('lemma', fn ($q) => $q->where('key_exact', $lemmaKey))
            ->exists();

        return $clash ? 'an entry with this lemma, homograph and part of speech exists' : null;
    }

    /** @return Generator<int, array<string, mixed>|string> record number => record, or an error message */
    private function records(string $path, Format $format): Generator
    {
        if ($format === Format::Json) {
            try {
                $document = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                yield 1 => 'invalid JSON: '.$e->getMessage();

                return;
            }

            $entries = is_array($document) && is_array($document['entries'] ?? null) ? $document['entries'] : [];

            foreach (array_values($entries) as $i => $entry) {
                yield $i + 1 => is_array($entry) ? $entry : 'not an object';
            }

            return;
        }

        $file = new SplFileObject($path);

        if ($format === Format::Csv) {
            // Proper CSV parsing: quoted fields may contain commas and newlines.
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
            $file->setCsvControl(',', '"', '');
            $header = null;

            foreach ($file as $index => $row) {
                if (! is_array($row) || $row === [null]) {
                    continue;
                }

                $row = array_map(static fn (mixed $c): string => (string) $c, $row);

                if ($header === null) {
                    $header = array_map(static fn (string $h): string => trim((string) preg_replace('/^\x{FEFF}/u', '', $h)), $row);

                    continue;
                }

                yield (int) $index + 1 => count($row) === count($header) ? array_combine($header, $row) : 'wrong number of columns';
            }

            return;
        }

        $number = 0;

        while (! $file->eof()) {
            $line = $file->fgets();
            $number++;

            if ($number === 1) {
                $line = (string) preg_replace('/^\x{FEFF}/u', '', $line);
            }

            if (trim($line) === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
                yield $number => is_array($record) ? $record : 'not an object';
            } catch (JsonException $e) {
                yield $number => 'invalid JSON: '.$e->getMessage();
            }
        }
    }
}
