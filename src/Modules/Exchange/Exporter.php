<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Modules\Exchange;

use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Models\Language;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Support\EntrySnapshot;
use InvalidArgumentException;

/**
 * Streams a language's entries to a file handle, in id order and in
 * chunks, so memory stays flat for any dictionary size. Only entries the
 * given ReadAccess may see are written; pass the reader's access, never
 * ReadAccess::everything() on behalf of a request.
 */
final class Exporter
{
    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** @param resource $handle */
    public function export(Language $language, $handle, Format $format, ReadAccess $access): int
    {
        if (! is_resource($handle)) {
            throw new InvalidArgumentException('Export needs an open, writable stream.');
        }

        $count = 0;

        if ($format === Format::Json) {
            fwrite($handle, '{"format":"atannex-lexicon","version":1,"language":'.json_encode(['code' => $language->code, 'name' => $language->name, 'native_name' => $language->native_name], self::JSON_FLAGS).',"entries":[');
        }

        if ($format === Format::Csv) {
            fputcsv($handle, ['uid', 'lemma', 'part_of_speech', 'homograph', 'variety', 'status', 'visibility', 'sense', 'definition_language', 'definition', 'forms'], escape: '');
        }

        $entries = Lexeme::query()
            ->where('language_id', $language->id)
            ->readable($access->visibilities, publishedOnly: ! $access->unpublished)
            ->with(EntrySnapshot::RELATIONS)
            ->lazyById(200);

        foreach ($entries as $lexeme) {
            $entry = EntrySnapshot::of($lexeme);

            match ($format) {
                Format::JsonLines => fwrite($handle, json_encode($entry, self::JSON_FLAGS)."\n"),
                Format::Json => fwrite($handle, ($count > 0 ? ',' : '').json_encode($entry, self::JSON_FLAGS)),
                Format::Csv => $this->csv($handle, $entry),
            };

            $count++;
        }

        if ($format === Format::Json) {
            fwrite($handle, ']}');
        }

        return $count;
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $entry
     */
    private function csv($handle, array $entry): void
    {
        /** @var list<array{text: string, kind: string}> $forms */
        $forms = $entry['forms'];
        $others = implode(' | ', array_map(static fn (array $f): string => $f['kind'].':'.$f['text'], array_filter($forms, static fn (array $f): bool => $f['kind'] !== 'lemma')));

        /** @var list<array{definitions: list<array{text: string, written_in: string}>}> $senses */
        $senses = $entry['senses'] ?: [['definitions' => []]];

        foreach ($senses as $index => $sense) {
            $definition = $sense['definitions'][0] ?? null;

            fputcsv($handle, [
                $entry['uid'], $entry['lemma'], $entry['part_of_speech'], $entry['homograph'], $entry['variety'],
                $entry['status'], $entry['visibility'], $index + 1,
                $definition['written_in'] ?? '', self::cell($definition['text'] ?? ''), $others,
            ], escape: '');
        }
    }

    /** Neutralise spreadsheet formulas without changing what a reader sees. */
    private static function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
