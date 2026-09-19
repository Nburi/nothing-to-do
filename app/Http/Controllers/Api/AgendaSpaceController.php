<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only: the classes/study groups the token's user belongs to, so a client can pick an
 * `agenda_space_id` when filing an Agenda entry. Joining, leaving and managing a class stay in the
 * app — and the invite code is deliberately not exposed here.
 */
class AgendaSpaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $spaces = $user->agendaSpaces()->withCount('members')->ordered()->get();

        return response()->json([
            'data' => $spaces->map(fn ($space) => [
                'id' => $space->id,
                'name' => $space->name,
                'members_count' => (int) $space->members_count,
                'is_owner' => $space->owner_id === $user->id,
            ])->values(),
        ]);
    }
}
