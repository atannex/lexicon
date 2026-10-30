<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Authorization;

/**
 * Gate abilities the lexicon checks. The package defines none of them:
 * your application grants each one (Gate::define) to whichever roles it
 * uses, and an ability nobody defined is denied.
 *
 *   Gate::define('lexicon.edit', fn (User $user) => $user->hasRole('linguist'));
 */
final class Abilities
{
    /** Read records with Visibility::Community. */
    public const string VIEW_COMMUNITY = 'lexicon.view-community';

    /** Read community-restricted knowledge (Visibility::Restricted). */
    public const string VIEW_RESTRICTED = 'lexicon.view-restricted';

    /** Read Visibility::Private records. */
    public const string VIEW_PRIVATE = 'lexicon.view-private';

    /** Read entries that are not published (drafts, submissions, archive). */
    public const string VIEW_UNPUBLISHED = 'lexicon.view-unpublished';

    /** Propose new entries and changes. */
    public const string CONTRIBUTE = 'lexicon.contribute';

    /** Change entries directly. */
    public const string EDIT = 'lexicon.edit';

    /** Review submissions (editorial module). */
    public const string REVIEW = 'lexicon.review';

    /** Publish, withdraw and archive entries. */
    public const string PUBLISH = 'lexicon.publish';

    /** Delete entries permanently. */
    public const string DELETE = 'lexicon.delete';

    /** Register languages, varieties and orthographies. */
    public const string MANAGE_LANGUAGES = 'lexicon.manage-languages';

    /** Export datasets (exchange module). */
    public const string EXPORT = 'lexicon.export';

    /** Import datasets (exchange module). */
    public const string IMPORT = 'lexicon.import';
}
