<?php

declare(strict_types=1);

use Atannex\Lexicon\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Core lexicon storage.
 *
 * Tenancy: every scoped table has tenant ('' = shared). A sentinel instead
 * of NULL keeps unique indexes identical on SQLite, MySQL and PostgreSQL.
 *
 * Spelling: original text is stored byte-for-byte in TEXT columns. Search
 * and sort keys are computed in PHP; on MySQL they use a binary collation
 * so the database never treats "a" and "à" as equal behind our back.
 *
 * Deletion: deleting a lexeme removes its forms, senses, definitions,
 * examples, relations and attestations. A source that is cited cannot be
 * deleted, and a language with entries cannot be deleted.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return Tables::connection();
    }

    public function up(): void
    {
        Schema::create(Tables::name('contributors'), function (Blueprint $table): void {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('tenant', 64)->default('');
            $table->string('user_id', 64)->nullable();
            $table->text('display_name');
            $table->text('credit_as')->nullable();
            $table->boolean('credit_publicly')->default(false);
            $table->json('roles')->nullable();
            $table->string('consent', 16)->default('unknown');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant', 'user_id']);
        });

        Schema::create(Tables::name('sources'), function (Blueprint $table): void {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('tenant', 64)->default('');
            $table->string('kind', 32);
            $table->text('title');
            $table->text('citation')->nullable();
            $table->json('authors')->nullable();
            $table->text('publisher')->nullable();
            $table->string('published', 32)->nullable();
            $table->string('url', 2048)->nullable();
            $table->date('collected_on')->nullable();
            $table->text('location')->nullable();
            $table->string('license', 100)->nullable();
            $table->string('consent', 16)->default('unknown');
            $table->string('visibility', 16)->default('private');
            $table->foreignId('contributor_id')->nullable()->constrained(Tables::name('contributors'))->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant', 'kind']);
        });

        Schema::create(Tables::name('languages'), function (Blueprint $table): void {
            $table->id();
            $table->string('tenant', 64)->default('');
            $table->string('code', 64);
            $table->text('name');
            $table->text('native_name')->nullable();
            $table->char('iso639_3', 3)->nullable();
            $table->json('external_ids')->nullable();
            $table->text('description')->nullable();
            $table->json('geography')->nullable();
            $table->string('documentation_status', 16)->default('undocumented');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant', 'code']);
            $table->index('iso639_3');
        });

        Schema::create(Tables::name('language_names'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('language_id')->constrained(Tables::name('languages'))->cascadeOnDelete();
            $table->text('name');
            $table->string('kind', 16)->default('alternative');
            $table->string('written_in', 35)->nullable();
            $table->foreignId('source_id')->nullable()->constrained(Tables::name('sources'))->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create(Tables::name('language_varieties'), function (Blueprint $table): void {
            $table->id();
            $table->string('tenant', 64)->default('');
            $table->foreignId('language_id')->constrained(Tables::name('languages'))->cascadeOnDelete();
            $table->string('code', 64);
            $table->text('name');
            $table->text('native_name')->nullable();
            $table->json('alternative_names')->nullable();
            $table->string('kind', 16)->default('dialect');
            $table->text('description')->nullable();
            $table->json('geography')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['language_id', 'code']);
        });

        Schema::create(Tables::name('language_relationships'), function (Blueprint $table): void {
            $table->id();
            $table->string('tenant', 64)->default('');
            $table->foreignId('from_language_id')->constrained(Tables::name('languages'))->cascadeOnDelete();
            $table->foreignId('to_language_id')->constrained(Tables::name('languages'))->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('certainty', 16)->default('proposed');
            $table->foreignId('source_id')->nullable()->constrained(Tables::name('sources'))->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant', 'from_language_id', 'to_language_id', 'type'], 'lex_lang_rel_unique');
        });

        Schema::create(Tables::name('orthographies'), function (Blueprint $table): void {
            $table->id();
            $table->string('tenant', 64)->default('');
            $table->foreignId('language_id')->nullable()->constrained(Tables::name('languages'))->cascadeOnDelete();
            $table->string('code', 64);
            $table->text('name');
            $table->string('kind', 16)->default('orthography');
            $table->string('script', 4)->default('Latn');
            $table->string('direction', 3)->default('ltr');
            $table->string('profile', 64)->nullable();
            $table->json('rules')->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            // language_id is NULL for notations shared by every language (IPA).
            $table->unique(['tenant', 'language_id', 'code']);
        });

        Schema::create(Tables::name('lexemes'), function (Blueprint $table): void {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('tenant', 64)->default('');
            $table->foreignId('language_id')->constrained(Tables::name('languages'))->restrictOnDelete();
            $table->foreignId('variety_id')->nullable()->constrained(Tables::name('language_varieties'))->nullOnDelete();
            $table->unsignedBigInteger('lemma_form_id')->nullable();
            $table->string('part_of_speech', 64)->nullable();
            $table->unsignedSmallInteger('homograph')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('visibility', 16)->default('private');
            $table->foreignId('contributor_id')->nullable()->constrained(Tables::name('contributors'))->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant', 'language_id', 'status', 'visibility']);
        });

        Schema::create(Tables::name('forms'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained(Tables::name('lexemes'))->cascadeOnDelete();
            // Copied from the lexeme so search can filter without a join.
            $table->string('tenant', 64)->default('');
            $table->unsignedBigInteger('language_id');
            $table->foreignId('variety_id')->nullable()->constrained(Tables::name('language_varieties'))->nullOnDelete();
            $table->foreignId('orthography_id')->nullable()->constrained(Tables::name('orthographies'))->nullOnDelete();
            $table->string('kind', 16);
            $table->text('text');
            $this->key($table->string('key_exact', 255));
            $this->key($table->string('key_toneless', 255));
            $this->key($table->string('key_bare', 255));
            $this->key($table->string('sort_key', 512));
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant', 'language_id', 'key_exact']);
            $table->index(['tenant', 'language_id', 'key_toneless']);
            $table->index(['tenant', 'language_id', 'key_bare']);
            $table->index(['lexeme_id', 'kind']);
        });

        Schema::table(Tables::name('lexemes'), function (Blueprint $table): void {
            $table->foreign('lemma_form_id')->references('id')->on(Tables::name('forms'))->nullOnDelete();
        });

        Schema::create(Tables::name('senses'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained(Tables::name('lexemes'))->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('semantic_domain', 128)->nullable();
            $table->json('labels')->nullable();
            $table->text('usage_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lexeme_id', 'position']);
        });

        Schema::create(Tables::name('definitions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sense_id')->constrained(Tables::name('senses'))->cascadeOnDelete();
            $table->unsignedBigInteger('lexeme_id')->index();
            $table->string('written_in', 35);
            $table->text('text');
            $table->text('search_exact');
            $table->text('search_toneless');
            $table->text('search_bare');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['sense_id', 'written_in']);
        });

        Schema::create(Tables::name('examples'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sense_id')->constrained(Tables::name('senses'))->cascadeOnDelete();
            $table->unsignedBigInteger('lexeme_id')->index();
            $table->foreignId('variety_id')->nullable()->constrained(Tables::name('language_varieties'))->nullOnDelete();
            $table->foreignId('orthography_id')->nullable()->constrained(Tables::name('orthographies'))->nullOnDelete();
            $table->text('text');
            $table->text('search_exact');
            $table->text('search_toneless');
            $table->text('search_bare');
            $table->text('context')->nullable();
            $table->string('audio', 1024)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create(Tables::name('example_translations'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('example_id')->constrained(Tables::name('examples'))->cascadeOnDelete();
            $table->string('written_in', 35);
            $table->text('text');
            $table->timestamps();
        });

        Schema::create(Tables::name('relations'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_lexeme_id')->constrained(Tables::name('lexemes'))->cascadeOnDelete();
            $table->foreignId('from_sense_id')->nullable()->constrained(Tables::name('senses'))->cascadeOnDelete();
            $table->foreignId('to_lexeme_id')->constrained(Tables::name('lexemes'))->cascadeOnDelete();
            $table->foreignId('to_sense_id')->nullable()->constrained(Tables::name('senses'))->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['from_lexeme_id', 'type']);
            $table->index(['to_lexeme_id', 'type']);
        });

        Schema::create(Tables::name('translation_equivalents'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sense_id')->constrained(Tables::name('senses'))->cascadeOnDelete();
            $table->string('written_in', 35);
            $table->text('text')->nullable();
            $table->foreignId('target_sense_id')->nullable()->constrained(Tables::name('senses'))->cascadeOnDelete();
            $table->string('type', 16)->default('exact');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create(Tables::name('attestations'), function (Blueprint $table): void {
            $table->id();
            $table->string('tenant', 64)->default('');
            $table->foreignId('source_id')->constrained(Tables::name('sources'))->restrictOnDelete();
            $table->string('attestable_type', 32);
            $table->unsignedBigInteger('attestable_id');
            $table->unsignedBigInteger('lexeme_id')->nullable()->index();
            $table->string('locator')->nullable();
            $table->text('quotation')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('contributor_id')->nullable()->constrained(Tables::name('contributors'))->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by', 64)->nullable();
            $table->timestamps();

            $table->index(['attestable_type', 'attestable_id']);
        });
    }

    public function down(): void
    {
        Schema::table(Tables::name('lexemes'), function (Blueprint $table): void {
            $table->dropForeign(['lemma_form_id']);
        });

        foreach (['attestations', 'translation_equivalents', 'relations', 'example_translations', 'examples', 'definitions', 'senses', 'forms', 'lexemes', 'orthographies', 'language_relationships', 'language_varieties', 'language_names', 'languages', 'sources', 'contributors'] as $table) {
            Schema::dropIfExists(Tables::name($table));
        }
    }

    /** Keys must compare byte-wise; MySQL's default collations fold accents. */
    private function key(ColumnDefinition $column): void
    {
        if (Schema::connection($this->getConnection())->getConnection()->getDriverName() === 'mysql') {
            $column->charset('utf8mb4')->collation('utf8mb4_bin');
        }
    }
};
