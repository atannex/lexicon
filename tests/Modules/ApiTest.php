<?php

declare(strict_types=1);

use Atannex\Lexicon\Actions\Attest;
use Atannex\Lexicon\Authorization\Abilities;
use Atannex\Lexicon\Data\CreateSourceData;
use Atannex\Lexicon\Enums\ConsentStatus;
use Atannex\Lexicon\Enums\SourceKind;
use Atannex\Lexicon\Enums\Visibility;
use Atannex\Lexicon\Facades\Lexicon;
use Atannex\Lexicon\Models\Lexeme;

beforeEach(function (): void {
    $this->language = language('testlang');
    $public = Lexicon::createSource(new CreateSourceData(
        kind: SourceKind::PublishedDictionary, title: 'Published word list', consent: ConsentStatus::NotRequired, visibility: Visibility::Public,
    ));
    $this->open = published($this->language, 'Ngangà', ['sourceId' => $public->id]);
    app(Attest::class)->handle($this->open, source(title: 'Private interview'));
    $this->restricted = published($this->language, 'Mbeqlěq', ['visibility' => Visibility::Restricted]);
    $this->draft = entry($this->language, 'Betaâgnúá');
});

it('lists languages and shows one with its varieties', function (): void {
    $this->getJson('/api/lexicon/v1/languages')->assertOk()->assertJsonPath('data.0.code', 'testlang');
    $this->getJson('/api/lexicon/v1/languages/testlang')->assertOk()->assertJsonPath('data.varieties', []);
    $this->getJson('/api/lexicon/v1/languages/missing')->assertNotFound();
});

it('lists only published public entries to anonymous readers', function (): void {
    $this->getJson('/api/lexicon/v1/languages/testlang/entries')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.lemma', 'Ngangà')
        ->assertJsonMissingPath('data.0.status')
        ->assertJsonPath('meta.total', 1);
});

it('answers 404 for entries the reader may not see', function (): void {
    $this->getJson("/api/lexicon/v1/entries/{$this->open->uid}")->assertOk()->assertJsonPath('data.sources.0.title', 'Published word list')->assertJsonCount(1, 'data.sources');
    $this->getJson("/api/lexicon/v1/entries/{$this->restricted->uid}")->assertNotFound();
    $this->getJson("/api/lexicon/v1/entries/{$this->draft->uid}")->assertNotFound();
});

it('shows restricted entries to users granted the ability', function (): void {
    grant([Abilities::VIEW_RESTRICTED], [5]);

    $this->actingAs(user(5))->getJson("/api/lexicon/v1/entries/{$this->restricted->uid}")->assertOk();
    $this->actingAs(user(6))->getJson("/api/lexicon/v1/entries/{$this->restricted->uid}")->assertNotFound();
});

it('searches with tenant, visibility and sensitivity applied', function (): void {
    $this->getJson('/api/lexicon/v1/search?q=nganga&sensitivity=tone_insensitive&language=testlang')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.entry.lemma', 'Ngangà');

    $this->getJson('/api/lexicon/v1/search?q=mbeq')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/lexicon/v1/search?q=')->assertUnprocessable();
});

it('requires authentication and permission to write', function (): void {
    grant([Abilities::EDIT, Abilities::DELETE], [1]);
    $payload = ['language' => 'testlang', 'lemma' => 'Mbeqńkœó', 'senses' => [['definitions' => [['text' => 'test gloss', 'written_in' => 'en']]]]];

    $this->postJson('/api/lexicon/v1/entries', $payload)->assertUnauthorized();
    $this->actingAs(user(2))->postJson('/api/lexicon/v1/entries', $payload)->assertForbidden();

    $uid = $this->actingAs(user(1))->postJson('/api/lexicon/v1/entries', $payload)->assertCreated()->assertJsonPath('data.lemma', 'Mbeqńkœó')->json('data.uid');

    $this->actingAs(user(1))->patchJson("/api/lexicon/v1/entries/{$this->open->uid}", ['part_of_speech' => 'noun'])->assertOk()->assertJsonPath('data.part_of_speech', 'noun');
    $this->actingAs(user(1))->deleteJson("/api/lexicon/v1/entries/{$this->open->uid}")->assertNoContent();

    expect(Lexeme::query()->where('uid', $uid)->exists())->toBeTrue()
        ->and($this->open->fresh())->toBeNull();
});

it('returns validation and domain errors as 422', function (): void {
    grant([Abilities::EDIT], [1]);

    $this->actingAs(user(1))->postJson('/api/lexicon/v1/entries', ['language' => 'testlang', 'lemma' => ''])->assertUnprocessable()->assertJsonValidationErrors('lemma');
    $this->actingAs(user(1))->postJson('/api/lexicon/v1/entries', ['language' => 'testlang', 'lemma' => 'x', 'variety' => 'nope'])->assertUnprocessable()->assertJsonPath('message', 'Language [testlang] has no variety [nope].');
});

it('accepts submissions from contributors when the editorial module is on', function (): void {
    grant([Abilities::CONTRIBUTE], [3]);

    $this->actingAs(user(3))->postJson('/api/lexicon/v1/submissions', [
        'language' => 'testlang',
        'kind' => 'add_form',
        'entry' => $this->open->uid,
        'payload' => ['text' => 'Nganga', 'kind' => 'variant'],
    ])->assertCreated()->assertJsonPath('data.status', 'submitted');

    $this->actingAs(user(4))->postJson('/api/lexicon/v1/submissions', ['language' => 'testlang', 'kind' => 'new_entry', 'payload' => ['lemma' => 'x']])->assertForbidden();
});

it('rate limits the API', function (): void {
    config(['lexicon.api.rate_limit' => 2]);

    $this->getJson('/api/lexicon/v1/languages')->assertOk();
    $this->getJson('/api/lexicon/v1/languages')->assertOk();
    $this->getJson('/api/lexicon/v1/languages')->assertTooManyRequests();
});

it('isolates tenants over HTTP', function (): void {
    $shared = language('shared', shared: true);
    $foreign = Lexicon::forTenant('other', fn () => published($shared, 'foreign'));

    $this->getJson("/api/lexicon/v1/entries/{$foreign->uid}")->assertNotFound();
});
