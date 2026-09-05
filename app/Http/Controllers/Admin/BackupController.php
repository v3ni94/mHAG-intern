<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backups (Abschnitt 129 Masterprompt): manuell anstoßen, Statuskarte,
 * Liste mit Größe und Datum, Download nur für Administratoren.
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('admin.backups.index', ['status' => $this->backups->status()]);
    }

    public function run(Request $request): RedirectResponse
    {
        $result = $this->backups->run();

        if ($result['success']) {
            return back()->with('success', sprintf('Backup wurde erstellt: %s.', $result['file']));
        }

        return back()->with('error', 'Backup fehlgeschlagen: '.($result['error'] ?? 'unbekannter Fehler'));
    }

    public function download(Request $request, string $file): BinaryFileResponse
    {
        // Download nur für Administratoren (über die Route-Middleware hinaus).
        abort_unless($request->user()->hasRole('Administrator'), 403);

        $path = $this->backups->filePath($file);
        abort_if($path === null, 404);

        AuditService::log('admin.backups.downloaded', null, [], ['file' => $file]);

        return response()->download($path);
    }
}
