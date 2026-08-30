<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function edit(Request $request): \Illuminate\View\View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'sessions' => $this->sessions($request),
            'loginHistory' => \App\Models\LoginAttempt::query()
                ->where('user_id', $request->user()->id)
                ->latest('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profil gespeichert.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
            ]);
        }

        $request->user()->forceFill(['password' => $validated['password']])->save();
        AuditService::log('auth.password_changed', $request->user());

        return back()->with('success', 'Passwort geändert.');
    }

    /** Datenschutzmodus: Geldbeträge ausblenden (Abschnitt 126). */
    public function togglePrivacyMode(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['privacy_mode' => ! $user->privacy_mode])->save();

        return back();
    }

    private function sessions(Request $request): \Illuminate\Support\Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->limit(10)
                ->get(),
        );
    }
}
