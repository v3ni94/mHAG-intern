<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use App\Models\LoanRecalculation;
use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Systemstatus (Abschnitt 136 Masterprompt): Versionen, Datenbank, Jobs,
 * Backups, SFTP, fehlgeschlagene Logins, Neuberechnungsfehler, Speicher.
 */
class SystemStatusController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(Request $request): View
    {
        $dbOk = true;
        $dbError = null;
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        $latestChangelog = ChangelogEntry::query()->orderByDesc('released_on')->orderByDesc('id')->first();

        return view('admin.status.index', [
            'appVersion' => $latestChangelog?->version ?? 'unbekannt',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'dbConnection' => config('database.default'),
            'dbOk' => $dbOk,
            'dbError' => $dbError,
            'openJobs' => $this->safeCount('jobs'),
            'failedJobs' => $this->safeCount('failed_jobs'),
            'backupStatus' => $this->backups->status(),
            'sftpStatus' => Setting::get('sftp', 'last_test'),
            'failedLogins24h' => LoginAttempt::query()
                ->where('successful', false)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'recalculationErrors' => LoanRecalculation::query()
                ->with('loan:id,loan_number')
                ->where('status', 'error')
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'diskFree' => @disk_free_space(storage_path()) ?: null,
            'diskTotal' => @disk_total_space(storage_path()) ?: null,
        ]);
    }

    private function safeCount(string $table): ?int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
