<?php

namespace App\Models;

use App\Support\Markdown\UnderlineExtension;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One Hilfe-Center page — admin-authored, read by every account once
 * published. See CLAUDE.md, "Hilfe-Center & Support".
 */
class HelpArticle extends Model
{
    /** A plain-text preview shows this many words before truncating with "…". */
    public const PREVIEW_WORDS = 24;

    protected $fillable = [
        'title',
        'content',
        'help_category_id',
        'created_by',
        'is_published',
        'published_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Publishing stamps published_at only the first time — unpublishing and
     * republishing later must not move it. Mirrors
     * FeatureAnnouncement::togglePublish()'s own rule.
     */
    public function togglePublished(): void
    {
        $this->update([
            'is_published' => ! $this->is_published,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Renders the body to safe HTML — full GitHub-flavoured Markdown
     * (Str::markdown wraps League\CommonMark's GithubFlavoredMarkdownConverter,
     * which already includes tables, autolinks, strikethrough and task lists),
     * plus the same ++underline++ extension and html_input=strip/
     * allow_unsafe_links=false safety options every other Markdown field in
     * this app uses (Task::renderNotesMarkdown, TaskGroup::renderNotes).
     *
     * A GFM task list renders its checkboxes with a hardcoded `disabled`
     * attribute — CommonMark has no notion of an interactive reader. Since a
     * reader ticking a checklist inside a help article (e.g. "Vor dem Start")
     * should be able to check things off for themselves without that state
     * persisting anywhere or leaking to other readers, stripping `disabled`
     * here is the entire implementation: an enabled checkbox with no
     * wire:model/localStorage/backing column already toggles on click and
     * already forgets that state on reload, simply because nothing anywhere
     * writes it down.
     */
    public static function renderMarkdown(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        $html = Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new UnderlineExtension()]);

        return preg_replace('/(<input\b[^>]*?)\s+disabled(=("|\')?[^"\'\s>]*("|\')?)?/i', '$1', $html);
    }

    /** The first few words of the body, stripped of Markdown syntax, for a list row. */
    public function preview(int $words = self::PREVIEW_WORDS): ?string
    {
        $text = trim((string) $this->content);

        if ($text === '') {
            return null;
        }

        $text = preg_replace('/^#{1,6}\s*/m', '', $text);
        $text = preg_replace('/^- \[[ xX]\] /m', '', $text);
        $text = preg_replace('/^[-*] /m', '', $text);
        $text = preg_replace('/^\|.*\|$/m', '', $text);
        $text = str_replace(['**', '++', '*', '`'], '', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return null;
        }

        $parts = explode(' ', $text);
        $preview = implode(' ', array_slice($parts, 0, $words));

        return count($parts) > $words ? $preview.'…' : $preview;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Title or body contains $term (case-insensitive substring). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', '%'.$term.'%')
                ->orWhere('content', 'like', '%'.$term.'%');
        });
    }
}
