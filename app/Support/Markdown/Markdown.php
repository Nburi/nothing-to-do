<?php

namespace App\Support\Markdown;

use Illuminate\Support\Str;

/**
 * The one safe Markdown renderer for every user-authored Markdown field in
 * this app — task/quick-capture notes, group notes, project brainstorming,
 * and Hilfe-Center articles. Full GitHub-flavoured Markdown (Str::markdown
 * wraps League CommonMark's GithubFlavoredMarkdownConverter: headings,
 * links, tables, autolinks, strikethrough, task lists) plus this app's own
 * ++underline++ syntax (UnderlineExtension — neither CommonMark nor GFM has
 * a native underline), with raw HTML stripped and unsafe link schemes
 * dropped so nothing a field contains can inject. One implementation so
 * every field gets the same capability by construction, instead of several
 * near-identical Str::markdown() calls that can silently drift apart.
 */
class Markdown
{
    public static function toHtml(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new UnderlineExtension()]);
    }
}
