<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard (Abschnitte 68, 69, 74, 136 Masterprompt).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('dashboard.view'), 403);

        return view('dashboard.index', [
            'todayRelevant' => $this->dashboard->todayRelevant($user),
            'kpis' => $this->dashboard->loanKpis($user),
            'charts' => $this->dashboard->charts($user),
            'adminOverview' => $user->can('admin.settings') ? $this->dashboard->adminOverview() : null,
        ]);
    }
}
