<?php

declare(strict_types=1);

use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Data\DefinitionData;
use Atannex\Lexicon\Data\ExampleData;
use Atannex\Lexicon\Data\FormData;
use Atannex\Lexicon\Data\SenseData;
use Atannex\Lexicon\Enums\EntryStatus;
use Atannex\Lexicon\Enums\FormKind;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Modules\Exchange\Exporter;
use Atannex\Lexicon\Modules\Exchange\Format;
use Atannex\Lexicon\Modules\Exchange\Importer;
use Atannex\Lexicon\Support\EntrySnapshot;

function exportTo(string $format, ReadAccess $access, string $code = 'source'): string
{
    $path = tempnam(sys_get_temp_dir(), 'lex').'.'.$format;
    $handle = fopen($path, 'wb');
    app(Exporter::class)->export(Lexicon::languages()->get($code), $handle, Format::from($format), $access);
    fclose($handle);

    return $path;
}

beforeEach(function (): void {
    $this->source = language('source');
    $this->target = language('target');

    published($this->source, 'Njœêlekœr̄', [
        'forms' => [new FormData("Ngang\u{0061}\u{0300}", FormKind::Historical)],
        'senses' => [new SenseData(
            definitions: [new DefinitionData('first test gloss', 'en'), new DefinitionData('=SUM(A1)', 'fr')],
            examples: [new ExampleData('Mbeqńkœó àbɔ́', ['en' => 'test example'])],
        )],
    ]);
    published($this->source, 'restricted entry', ['visibility' => Visibility::Restricted]);
    entry($this->source, 'draft entry');
});

it('round-trips entries through JSON Lines without losing a byte', function (): void {
    $path = exportTo('jsonl', ReadAccess::public());
    $report = app(Importer::class)->import($this->target, $path, Format::JsonLines);

    $original = EntrySnapshot::of(Lexeme::query()->where('language_id', $this->source->id)->where('visibility', 'public')->where('status', 'published')->firstOrFail());
    $copy = EntrySnapshot::of(Lexeme::query()->where('language_id', $this->target->id)->firstOrFail());

    expect($report->created)->toBe(1)
        ->and(file($path))->toHaveCount(1)
        ->and($copy['lemma'])->toBe('Njœêlekœr̄')
        ->and($copy['forms'][1]['text'])->toBe("Ngang\u{0061}\u{0300}")
        ->and($copy['senses'])->toBe($original['senses'])
        ->and($copy['status'])->toBe(EntryStatus::Draft->value);
});

it('exports only what the access allows', function (): void {
    expect(file(exportTo('jsonl', ReadAccess::public())))->toHaveCount(1)
        ->and(file(exportTo('jsonl', new ReadAccess([Visibility::Public, Visibility::Restricted]))))->toHaveCount(2)
        ->and(file(exportTo('jsonl', ReadAccess::everything())))->toHaveCount(3);
});

it('writes a JSON document and imports it', function (): void {
    $path = exportTo('json', ReadAccess::everything());
    $document = json_decode((string) file_get_contents($path), true);

    expect($document['format'])->toBe('atannex-lexicon')
        ->and($document['entries'])->toHaveCount(3)
        ->and(app(Importer::class)->import($this->target, $path, Format::Json)->created)->toBe(3);
});

it('writes CSV with formula cells neutralised and imports it', function (): void {
    $path = exportTo('csv', ReadAccess::public());
    $csv = (string) file_get_contents($path);

    expect($csv)->toContain('Njœêlekœr̄')->not->toContain(',=SUM');

    $report = app(Importer::class)->import($this->target, $path, Format::Csv);

    expect($report->created)->toBe(1)
        ->and(Lexeme::query()->where('language_id', $this->target->id)->firstOrFail()->senses()->firstOrFail()->definitions()->value('text'))->toBe('first test gloss');
});

it('is idempotent and reports duplicates instead of overwriting', function (): void {
    $path = exportTo('jsonl', ReadAccess::everything());

    $first = app(Importer::class)->import($this->target, $path, Format::JsonLines);
    $second = app(Importer::class)->import($this->target, $path, Format::JsonLines);

    expect($first->created)->toBe(3)
        ->and($second->created)->toBe(0)
        ->and($second->skipped)->toHaveCount(3)
        ->and(Lexeme::query()->where('language_id', $this->target->id)->count())->toBe(3);
});

it('validates in a dry run without writing anything', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'lex');
    file_put_contents($path, implode("\n", [
        json_encode(['lemma' => 'Ngangà', 'senses' => [['definitions' => [['text' => 'x', 'written_in' => 'en']]]]], JSON_UNESCAPED_UNICODE),
        '{not json',
        json_encode(['lemma' => '']),
        json_encode(['lemma' => 'Ngangà', 'senses' => [['definitions' => [['text' => 'y', 'written_in' => 'en']]]]], JSON_UNESCAPED_UNICODE),
    ]));

    $report = app(Importer::class)->import($this->target, $path, Format::JsonLines, dryRun: true);

    expect($report->created)->toBe(1)
        ->and(array_keys($report->failed))->toBe([2, 3])
        ->and(array_keys($report->skipped))->toBe([4])
        ->and(Lexeme::query()->where('language_id', $this->target->id)->count())->toBe(0);
});

it('attests every imported entry to the import source', function (): void {
    $path = exportTo('jsonl', ReadAccess::public());
    $source = source(title: 'Imported word list');

    app(Importer::class)->import($this->target, $path, Format::JsonLines, source: $source);

    expect($source->attestations()->count())->toBe(1);
});

it('exposes import and export as commands that refuse to overwrite', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'lex');

    $this->artisan('lexicon:export', ['language' => 'source', 'path' => $path])->assertFailed();
    $this->artisan('lexicon:export', ['language' => 'source', 'path' => $path, '--force' => true, '--include' => 'public,restricted'])->expectsOutputToContain('Exported 2')->assertSuccessful();
    $this->artisan('lexicon:import', ['language' => 'target', 'path' => $path, '--dry-run' => true])->expectsOutputToContain('Would create 2')->assertSuccessful();
    $this->artisan('lexicon:import', ['language' => 'target', 'path' => $path])->expectsOutputToContain('Created 2')->assertSuccessful();
    $this->artisan('lexicon:export', ['language' => 'source', 'path' => $path, '--force' => true, '--include' => 'secret'])->assertFailed();
});
