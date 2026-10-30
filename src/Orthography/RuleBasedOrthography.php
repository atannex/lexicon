<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Orthography;

use Atannex\Lexicon\Contracts\OrthographyProfile;
use Atannex\Lexicon\Enums\MatchSensitivity;
use Atannex\Lexicon\Support\Unicode;
use Collator;

/**
 * The profile used for every orthography unless an application registers
 * its own. With empty rules it is plain Unicode: NFC comparison, full case
 * folding, and the root collation of the Unicode Collation Algorithm.
 */
final class RuleBasedOrthography implements OrthographyProfile
{
    private ?Collator $collator = null;

    /** @var list<string>|null alphabet units, longest first, for greedy matching */
    private ?array $units = null;

    public function __construct(
        private readonly OrthographyRules $rules = new OrthographyRules,
        private readonly string $key = 'neutral',
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function rules(): OrthographyRules
    {
        return $this->rules;
    }

    public function keys(string $text): SearchKeys
    {
        return new SearchKeys(
            exact: $this->searchKey($text, MatchSensitivity::Exact),
            toneless: $this->searchKey($text, MatchSensitivity::ToneInsensitive),
            bare: $this->searchKey($text, MatchSensitivity::DiacriticInsensitive),
            sort: $this->sortKey($text),
        );
    }

    public function searchKey(string $text, MatchSensitivity $sensitivity): string
    {
        $folded = Unicode::fold(trim($text));

        return match ($sensitivity) {
            MatchSensitivity::Exact => $folded,
            MatchSensitivity::ToneInsensitive => Unicode::stripMarks($folded, $this->rules->toneMarks),
            MatchSensitivity::DiacriticInsensitive => $this->bare($folded),
        };
    }

    public function sortKey(string $text): string
    {
        $folded = Unicode::fold(trim($text));

        return $this->rules->alphabet === null
            ? bin2hex((string) $this->collator()->getSortKey($folded))
            : $this->alphabetKey($folded);
    }

    public function tokenize(string $text): array
    {
        $joiners = implode('', array_map(static fn (string $c): string => preg_quote($c, '/'), $this->rules->wordJoiners));
        $pattern = $joiners === '' ? '/[\s\p{P}\p{S}]+/u' : '/(?:(?!['.$joiners.'])[\s\p{P}\p{S}])+/u';

        $tokens = preg_split($pattern, Unicode::nfc($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map(fn (string $t): string => trim($t, implode('', $this->rules->wordJoiners)), $tokens),
            static fn (string $t): bool => $t !== '',
        ));
    }

    public function transliterate(string $text): ?string
    {
        if ($this->rules->transliteration === []) {
            return null;
        }

        return $this->replaceUnits(Unicode::fold($text), $this->rules->transliteration);
    }

    public function violations(string $text): array
    {
        if ($this->rules->inventory === null) {
            return [];
        }

        $inventory = array_flip($this->rules->inventory);
        $joiners = array_flip($this->rules->wordJoiners);
        $bad = [];

        foreach (Unicode::graphemes(Unicode::fold($text)) as $grapheme) {
            if (isset($inventory[$grapheme]) || isset($joiners[$grapheme]) || preg_match('/^\s$/u', $grapheme)) {
                continue;
            }

            // Accept a base letter from the inventory carrying tone marks.
            $toneless = Unicode::stripMarks($grapheme, $this->rules->toneMarks);

            if ($toneless !== $grapheme && isset($inventory[$toneless])) {
                continue;
            }

            $bad[$grapheme] = true;
        }

        return array_map('strval', array_keys($bad));
    }

    private function bare(string $folded): string
    {
        $bare = Unicode::stripMarks($folded);

        if ($this->rules->equivalences === []) {
            return $bare;
        }

        $map = [];

        foreach ($this->rules->equivalences as $from => $to) {
            $map[Unicode::stripMarks((string) $from)] = Unicode::stripMarks($to);
        }

        return $this->replaceUnits($bare, $map);
    }

    /**
     * Replace units longest-first, left to right, so "ng" is not split by a
     * rule for "n".
     *
     * @param  array<string, string>  $map
     */
    private function replaceUnits(string $text, array $map): string
    {
        return strtr($text, $map);
    }

    private function alphabetKey(string $folded): string
    {
        $alphabet = $this->rules->alphabet ?? [];
        $index = array_flip($alphabet);
        $units = $this->units ??= self::longestFirst($alphabet);

        // Primary: letters without tone; secondary: the tone-marked spelling,
        // so "ba" < "bà" < "bb" as in most printed dictionaries.
        $base = Unicode::stripMarks($folded, $this->rules->toneMarks);
        $primary = '';
        $offset = 0;
        $length = mb_strlen($base);

        while ($offset < $length) {
            foreach ($units as $unit) {
                $size = mb_strlen($unit);

                if (mb_substr($base, $offset, $size) === $unit) {
                    $primary .= sprintf('%06x', $index[$unit]);
                    $offset += $size;

                    continue 2;
                }
            }

            // Characters outside the alphabet sort after it, by code point.
            $primary .= sprintf('%06x', count($alphabet) + (mb_ord(mb_substr($base, $offset, 1)) ?: 0));
            $offset++;
        }

        return $primary.'|'.bin2hex($folded);
    }

    /**
     * @param  list<string>  $units
     * @return list<string>
     */
    private static function longestFirst(array $units): array
    {
        usort($units, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $units;
    }

    private function collator(): Collator
    {
        return $this->collator ??= new Collator('root');
    }
}
