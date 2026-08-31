<?php

namespace App\Models;

use App\Support\Markdown\Markdown;
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
        'slug',
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
     * A URL-safe, unique slug derived from $title — re-rolls with a "-2",
     * "-3", … suffix on collision rather than letting the unique index
     * throw, the same pattern AgendaSpace::generateInviteCode() already uses
     * for its own collision handling. $ignoreId excludes the article being
     * edited from its own collision check. An empty/all-punctuation title
     * (e.g. the placeholder "Neuer Artikel" stripped of nothing, or a title
     * of just emoji) falls back to "artikel" rather than persisting a blank
     * slug — the public route has nothing to key on otherwise.
     */
    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'artikel';
        }

        $slug = $base;
        $suffix = 2;

        while (
            self::where('slug', $slug)
                ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
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
     * Renders the body to safe HTML via the app's one shared Markdown
     * renderer (see App\Support\Markdown\Markdown) — full GitHub-flavoured
     * Markdown (headings, links, tables, autolinks, strikethrough, task
     * lists) plus the ++underline++ extension, same as every other Markdown
     * field in the app (Task::renderNotesMarkdown, TaskGroup::renderNotes,
     * ProjectPage::brainstormHtml).
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
        $html = Markdown::toHtml($text);

        if ($html === '') {
            return '';
        }

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
