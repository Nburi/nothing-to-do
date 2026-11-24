<?php

namespace App\Models\Concerns;

use App\Services\Planning\BoardOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Step 4.8 (C31): the model sits in the user's one shared board order (tasks, projects and groups
 * share the number space of `board_position`; lower = further up). A new row goes to the bottom, so
 * the board stays calm; rows without a position (completed work that was reopened) sort last.
 */
trait HasBoardPosition
{
    public static function bootHasBoardPosition(): void
    {
        static::creating(function ($model) {
            if ($model->board_position === null && $model->user_id !== null) {
                $model->board_position = BoardOrder::nextPosition((int) $model->user_id);
            }
        });
    }

    /** The shared order first; the older keys only settle ties and rows that have no position yet. */
    public function scopeInBoardOrder(Builder $query): Builder
    {
        return $query->orderByRaw('board_position IS NULL')->orderBy('board_position')->orderBy('sort_order')->orderBy('id');
    }
}
