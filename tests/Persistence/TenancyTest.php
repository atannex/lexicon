<?php

declare(strict_types=1);

use Atannex\Lexicon\Actions\Attest;
use Atannex\Lexicon\Actions\RelateLexemes;
use Atannex\Lexicon\Authorization\ReadAccess;
use Atannex\Lexicon\Enums\RelationType;
use Atannex\Lexicon\Exceptions\DuplicateRecord;
use Atannex\Lexicon\Exceptions\IntegrityViolation;
use Atannex\Lexicon\Exceptions\UnknownLanguage;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Lexeme;
use Atannex\Lexicon\Models\Source;

it('shares language identities but keeps entries per tenant', function (): void {
    $shared = language('shared', shared: true);

    $a = Lexicon::forTenant('a', fn () => published($shared, 'Ngangà'));
    Lexicon::forTenant('b', fn () => published($shared, 'Mbeqlěq'));

    Lexicon::forTenant('a', function () use ($a): void {
        expect(Lexicon::language('shared')->entries()->get()->map->lemmaText()->all())->toBe(['Ngangà'])
            ->and(lemmas(Lexicon::search('m')->get()))->toBe([])
            ->and(Lexicon::language('shared')->entries()->find($a->uid))->not->toBeNull();
    });

    Lexicon::forTenant('b', function () use ($a): void {
        expect(Lexicon::language('shared')->entries()->find($a->uid))->toBeNull()
            ->and(Lexeme::query()->where('uid', $a->uid)->exists())->toBeFalse()
            ->and(Lexicon::language('shared')->entries()->withAccess(ReadAccess::everything())->count())->toBe(1);
    });
});

it('hides one tenant\'s own languages from another', function (): void {
    Lexicon::forTenant('a', fn () => language('private'));

    Lexicon::forTenant('b', fn () => Lexicon::language('private'));
})->throws(UnknownLanguage::class);

it('keeps codes unambiguous across shared and tenant scopes', function (): void {
    language('nweh-test', shared: true);

    Lexicon::forTenant('a', fn () => language('nweh-test'));
})->throws(DuplicateRecord::class);

it('refuses to write to another tenant\'s entry loaded around the scope', function (): void {
    $shared = language('shared', shared: true);
    $a = Lexicon::forTenant('a', fn () => entry($shared, 'Ngangà'));

    Lexicon::forTenant('b', function () use ($a, $shared): void {
        $leaked = Lexeme::query()->withoutGlobalScopes()->findOrFail($a->id);

        expect(fn () => Lexicon::language($shared)->entries()->delete($leaked))->toThrow(IntegrityViolation::class);
    });

    expect(Lexeme::query()->withoutGlobalScopes()->whereKey($a->id)->exists())->toBeTrue();
});

it('refuses references to another tenant\'s sources and entries', function (): void {
    $shared = language('shared', shared: true);
    $foreignSource = Lexicon::forTenant('a', fn () => source());
    $foreignEntry = Lexicon::forTenant('a', fn () => entry($shared, 'Ngangà'));

    Lexicon::forTenant('b', function () use ($shared, $foreignSource, $foreignEntry): void {
        $own = entry($shared, 'Mbeqlěq');

        expect(fn () => entry($shared, 'x', ['sourceId' => $foreignSource->uid]))->toThrow(IntegrityViolation::class)
            ->and(fn () => app(Attest::class)->handle($own, Source::query()->withoutGlobalScopes()->findOrFail($foreignSource->id)))->toThrow(IntegrityViolation::class)
            ->and(fn () => app(RelateLexemes::class)->handle($own, RelationType::Synonym, $foreignEntry))->toThrow(IntegrityViolation::class);
    });
});

it('rejects malformed tenant keys', function (): void {
    Lexicon::forTenant("a'; drop table", fn () => null);
})->throws(InvalidArgumentException::class);
