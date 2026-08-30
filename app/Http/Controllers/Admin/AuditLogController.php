<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Audit-Trail (Abschnitt 120 Masterprompt): Filter nach Benutzer, Aktion,
 * Zeitraum und Objekttyp; Detailansicht mit alten und neuen Werten.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->query('auditable_type')))
            ->when($request->filled('von'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('von')))
            ->when($request->filled('bis'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('bis')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'objectTypes' => AuditLog::query()->select('auditable_type')->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }

    public function show(AuditLog $audit): View
    {
        $audit->load('user:id,name');

        return view('admin.audit.show', ['log' => $audit]);
    }
}
