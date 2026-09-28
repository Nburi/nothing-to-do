<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\DataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Download everything the user has written into the app as one JSON file.
     * A GET on purpose (it changes nothing, and a plain link works without JS);
     * no-store so a shared machine's cache never keeps a copy of someone's data.
     */
    public function export(Request $request): StreamedResponse
    {
        $data = DataExport::for($request->user());
        $filename = 'nothing-to-do-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            function () use ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            },
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store, private'],
        );
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Must run before delete(): agenda_spaces.owner_id's cascadeOnDelete
        // would otherwise silently destroy any shared class agenda this user
        // owns instead of handing it to the next member — see the method doc.
        $user->reassignOwnedAgendaSpaces();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
