<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder in the Hilfe-Center sidebar (see CLAUDE.md, "Hilfe-Center &
 * Support"). Self-referencing parent_id gives one level of subfolders;
 * nothing stops deeper nesting in the data, but tree()/adminTree() below
 * (and the sidebar view) only ever render two levels for now.
 */
class HelpCategory extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class)->orderBy('sort_order');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Root categories with their subcategories and articles eager-loaded —
     * the whole sidebar in one shot, no N+1. $publishedOnly limits articles
     * to published ones (the reader's Help view); the admin editor passes
     * false to see drafts too.
     *
     * @return Collection<int, self>
     */
    public static function tree(bool $publishedOnly): Collection
    {
        $articleScope = fn ($query) => $publishedOnly
            ? $query->published()->orderBy('sort_order')
            : $query->orderBy('sort_order');

        return self::roots()->ordered()
            ->with([
                'articles' => $articleScope,
                'children' => fn ($query) => $query->ordered(),
                'children.articles' => $articleScope,
            ])
            ->get();
    }
}
