<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()
            ->ordered()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('activeTasks')
            ->get();

        return ProjectResource::collection($projects)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = $request->user()->projects()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('tasks')
            ->findOrFail($id);

        return (new ProjectResource($project))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ]);

        $project = $request->user()->projects()->create([
            'name' => trim($data['name']),
            'deadline' => $data['deadline'] ?? null,
            'sort_order' => 0,
        ]);

        return (new ProjectResource($project->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $project = $request->user()->projects()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'brainstorm' => ['sometimes', 'nullable', 'string', 'max:50000'],
        ]);

        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = trim($data['name']);
        }
        if (array_key_exists('deadline', $data)) {
            $updates['deadline'] = $data['deadline'];
        }
        if (array_key_exists('external_url', $data)) {
            $updates['external_url'] = $data['external_url'] !== null ? trim($data['external_url']) : null;
        }
        if (array_key_exists('brainstorm', $data)) {
            $text = trim((string) $data['brainstorm']);
            $updates['brainstorm'] = $text !== '' ? $text : null;
        }

        $project->update($updates);

        return (new ProjectResource($project->fresh()))->response();
    }

    /** Release active tasks back to the inbox, then delete the project (mirrors the native app). */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $project = $request->user()->projects()->findOrFail($id);

        $request->user()->tasks()
            ->where('project_id', $project->id)
            ->where('is_completed', false)
            ->update(['project_id' => null, 'list' => 'inbox']);

        $project->delete();

        return response()->json(null, 204);
    }
}
