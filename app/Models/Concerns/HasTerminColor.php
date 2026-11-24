<?php

namespace App\Models\Concerns;

/**
 * The colour a Termin (a block without a category) wears in the week editor: one of the palette keys
 * of ScheduleEvent::EVENT_COLORS, or null for the current look (the dark "Standard" block).
 *
 * `color` stays what it always was (NOT NULL, default 'contour', read by the old screens, the API and
 * MCP). Anything that writes an explicit `color` on a category-less row (the old forms, API, MCP)
 * would otherwise be invisible in the grid, so the models copy it into termin_color on save unless
 * the writer set termin_color itself (the week editor does, and writes null for "Standard").
 * An occurrence row of a repeating block (template_id) never copies: it follows its template.
 */
trait HasTerminColor
{
    public static function bootHasTerminColor(): void
    {
        static::saving(function ($model) {
            if ($model->category_id === null
                && ($model->template_id ?? null) === null
                && $model->isDirty('color')
                && ! $model->isDirty('termin_color')) {
                $model->termin_color = $model->color;
            }
        });
    }
}
