<?php

namespace App\Models;

use App\Support\Markdown\UnderlineExtension;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Admin-authored long-form content — Blog/Doc/Leitfaden — written in
 * App\Livewire\Admin\ArticleEditor and read by every account in
 * App\Livewire\Library / App\Livewire\ArticleShow (see CLAUDE.md,
 * "Bibliothek — Blog, Docs & Leitfäden"). Not user-owned: there is no
 * user_id, visibility is governed by is_published alone, same draft/publish
 * shape as FeatureAnnouncement.
 */
class Article extends Model
{
    /**
     * @var array<string, array{label: string, tone: string}>
     */
    public const TYPES = [
        'blog' => ['label' => 'Blogpost', 'tone' => 'forest'],
        'doc' => ['label' => 'Doc', 'tone' => 'contour'],
        'guideline' => ['label' => 'Leitfaden', 'tone' => 'ink'],
    ];

    public const DEFAULT_TYPE = 'doc';

    /** A plain-text preview shows this many words before truncating with "…". */
    public const PREVIEW_WORDS = 24;

    protected $fillable = [
        'title',
        'type',
        'content',
        'created_by',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** This article's TYPES entry, falling back to the default for a stale/unknown value. */
    public function typeMeta(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES[self::DEFAULT_TYPE];
    }

    public function typeLabel(): string
    {
        return $this->typeMeta()['label'];
    }

    /**
     * Publishing stamps published_at only the first time — unpublishing and
     * republishing later must not move it, since it marks when the piece was
     * actually introduced, not the current toggle state. Mirrors
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
     * which already includes tables, autolinks, strikethrough and task lists
     * with no extra config), plus the same ++underline++ extension and
     * html_input=strip/allow_unsafe_links=false safety options every other
     * markdown field in this app uses (Task::renderNotesMarkdown,
     * TaskGroup::renderNotes).
     *
     * A GFM task list ("- [ ] Schritt 1") renders its checkboxes with a
     * hardcoded `disabled` attribute — CommonMark's own TaskListExtension
     * always emits it, since it has no notion of an interactive reader. This
     * app wants the opposite: a reader ticking a checklist inside a Blog/Doc/
     * Leitfaden should be able to check things off for themselves, but that
     * state must never be shared between readers or survive a reload (see
     * CLAUDE.md). Stripping `disabled` here is the entire implementation —
     * an enabled checkbox with no wire:model/localStorage/backing store
     * already toggles on click and already forgets that state on reload,
     * simply because nothing anywhere writes it down. No JS needed.
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

    /**
     * The first few words of the body, stripped of Markdown syntax, for the
     * list row — mirrors Task::notesPreview()'s approach.
     */
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

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type !== null ? $query->where('type', $type) : $query;
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

    /** Newest published first — what a reader actually wants: what's new. */
    public function scopePublishedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
