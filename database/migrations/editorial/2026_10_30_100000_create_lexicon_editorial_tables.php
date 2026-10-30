<?php

declare(strict_types=1);

use Atannex\Lexicon\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial module. Revisions keep the entry's uid rather than a foreign
 * key, so the history survives the entry being deleted.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return Tables::connection();
    }

    public function up(): void
    {
        Schema::create(Tables::name('submissions'), function (Blueprint $table): void {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('tenant', 64)->default('');
            $table->foreignId('language_id')->constrained(Tables::name('languages'))->cascadeOnDelete();
            $table->foreignId('lexeme_id')->nullable()->constrained(Tables::name('lexemes'))->nullOnDelete();
            $table->string('kind', 32);
            $table->json('payload');
            $table->string('status', 24)->default('submitted');
            $table->text('note')->nullable();
            $table->foreignId('source_id')->nullable()->constrained(Tables::name('sources'))->nullOnDelete();
            $table->foreignId('contributor_id')->nullable()->constrained(Tables::name('contributors'))->nullOnDelete();
            $table->string('submitted_by', 64);
            $table->string('decided_by', 64)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->timestamps();

            $table->index(['tenant', 'status', 'language_id']);
        });

        Schema::create(Tables::name('reviews'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained(Tables::name('submissions'))->cascadeOnDelete();
            $table->string('reviewer', 64);
            $table->string('decision', 16);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create(Tables::name('revisions'), function (Blueprint $table): void {
            $table->id();
            $table->string('tenant', 64)->default('');
            $table->string('lexeme_uid', 26);
            $table->unsignedInteger('version');
            $table->string('change', 32);
            $table->json('snapshot')->nullable();
            $table->string('actor', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant', 'lexeme_uid', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['revisions', 'reviews', 'submissions'] as $table) {
            Schema::dropIfExists(Tables::name($table));
        }
    }
};
