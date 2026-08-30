<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-App-Benachrichtigungen (Abschnitt 127 Masterprompt).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Alle Benachrichtigungen wurden als gelesen markiert.');
    }
}
