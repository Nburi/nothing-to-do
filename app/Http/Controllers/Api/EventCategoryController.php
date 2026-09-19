<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventCategoryResource;
use App\Models\AgendaEntry;
use App\Models\ScheduleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = $request->user()->eventCategories()->ordered()->with('pinnedTasks')->get();

        return EventCategoryResource::collection($categories)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', Rule::in(ScheduleEvent::EVENT_COLORS)],
            'pomodoro_enabled' => ['sometimes', 'boolean'],
        ]);

        $category = $request->user()->eventCategories()->create([
            'name' => trim($data['name']),
            'color' => $data['color'],
            'pomodoro_enabled' => $data['pomodoro_enabled'] ?? false,
            'sort_order' => $request->user()->eventCategories()->count(),
        ]);

        return (new EventCategoryResource($category->fresh('pinnedTasks')))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = $request->user()->eventCategories()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', Rule::in(ScheduleEvent::EVENT_COLORS)],
            'pomodoro_enabled' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = trim($data['name']);
        }
        if (array_key_exists('color', $data)) {
            $updates['color'] = $data['color'];
        }
        if (array_key_exists('pomodoro_enabled', $data)) {
            $updates['pomodoro_enabled'] = $data['pomodoro_enabled'];
        }

        $category->update($updates);

        return (new EventCategoryResource($category->fresh('pinnedTasks')))->response();
    }

    /**
     * Points a Pomodoro-enabled category at what its focus sessions should suggest — the same six
     * mutually-exclusive sources as Settings' link sheet, replacing whatever was linked before
     * (EventCategory::clearTaskLink() runs first, so a stale FK or pin can never survive a switch):
     *
     *   source=tasks          + task_ids (ordered — that order is the suggestion order)
     *   source=project        + project_id
     *   source=group          + group_id
     *   source=agenda_entry   + agenda_entry_id (an entry the user can see)
     *   source=agenda_generic   (nudges "Hausaufgaben erledigen")
     *   source=text           + text (free label, max 255)
     */
    public function setTaskLink(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = $user->eventCategories()->findOrFail($id);

        if (! $category->pomodoro_enabled) {
            throw ValidationException::withMessages([
                'source' => 'Only a category with pomodoro_enabled can be linked — the link only feeds its focus sessions.',
            ]);
        }

        $data = $request->validate([
            'source' => ['required', Rule::in(['tasks', 'project', 'group', 'agenda_entry', 'agenda_generic', 'text'])],
            'task_ids' => ['sometimes', 'array'],
            'task_ids.*' => ['integer', 'distinct', Rule::exists('tasks', 'id')->where('user_id', $user->id)],
            'project_id' => ['required_if:source,project', 'nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $user->id)],
            'group_id' => ['required_if:source,group', 'nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', $user->id)],
            'agenda_entry_id' => ['required_if:source,agenda_entry', 'nullable', 'integer'],
            'text' => ['required_if:source,text', 'nullable', 'string', 'max:255'],
        ]);

        $attributes = ['task_source' => $data['source']];

        switch ($data['source']) {
            case 'project':
                $attributes['linked_project_id'] = $data['project_id'];
                break;
            case 'group':
                $attributes['linked_group_id'] = $data['group_id'];
                break;
            case 'agenda_entry':
                // visibleTo(), not an ownership check: a classmate's entry is a valid link target too.
                $attributes['linked_agenda_entry_id'] = AgendaEntry::visibleTo($user)->find($data['agenda_entry_id'])?->id
                    ?? throw ValidationException::withMessages(['agenda_entry_id' => 'The selected agenda entry is invalid.']);
                break;
            case 'text':
                $attributes['linked_text'] = trim($data['text']);
                break;
        }

        $category->clearTaskLink();
        $category->update($attributes);

        if ($data['source'] === 'tasks') {
            $category->pinnedTasks()->sync(
                collect($data['task_ids'] ?? [])
                    ->values()
                    ->mapWithKeys(fn (int $taskId, int $order) => [$taskId => ['sort_order' => $order]])
                    ->all()
            );
        }

        return (new EventCategoryResource($category->fresh('pinnedTasks')))->response();
    }

    /** Removes the category's task link entirely (back to the generic suggestion tiers). */
    public function clearTaskLink(Request $request, int $id): JsonResponse
    {
        $category = $request->user()->eventCategories()->findOrFail($id);

        $category->clearTaskLink();

        return (new EventCategoryResource($category->fresh('pinnedTasks')))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->eventCategories()->whereKey($id)->delete();

        return response()->json(null, 204);
    }
}
