<?php

namespace App\Services\Projects;

/**
 * Step 4.3b: #tags in a note. A tag is a hash followed by a letter and up to 49 letters, digits,
 * "_" or "-", not glued to a word, an URL or an entity ("a#b", "x.ch/#anker", "&#38;", "#42" and
 * "# Überschrift" are no tags). Names are lower case. The data migration 2026_11_18_000002 keeps
 * its own copy of the pattern.
 */
final class NoteTags
{
    public const PATTERN = '/(?<![\p{L}\p{N}_&#\/])#(\p{L}[\p{L}\p{N}_-]{0,49})/u';

    /** @return list<string> unique, lower case, in order of first use */
    public static function parse(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $m);

        return array_values(array_unique(array_map(fn (string $t) => mb_strtolower($t), $m[1])));
    }

    /** Wrap each tag in rendered HTML, never inside a tag's markup, a link or code. */
    public static function mark(string $html): string
    {
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $skip = 0;
        $out = '';

        foreach ($parts as $part) {
            if ($part[0] === '<') {
                if (preg_match('#^<(a|code|pre)[\s>]#i', $part)) {
                    $skip++;
                } elseif (preg_match('#^</(a|code|pre)>#i', $part)) {
                    $skip = max(0, $skip - 1);
                }
                $out .= $part;

                continue;
            }

            $out .= $skip > 0 ? $part : preg_replace_callback(self::PATTERN, fn ($m) => '<span class="note-tag" data-tag="'.e(mb_strtolower($m[1])).'">#'.$m[1].'</span>', $part);
        }

        return $out;
    }
}
