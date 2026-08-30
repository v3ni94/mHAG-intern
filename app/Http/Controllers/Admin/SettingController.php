<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SettingsUpdateRequest;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Systemeinstellungen: 2FA-Pflichtrollen (Abschnitt 16), Verrechnungs-
 * reihenfolge (Abschnitt 47), Upload-Limits (Abschnitt 131).
 */
class SettingController extends Controller
{
    public const DEFAULT_ALLOCATION_ORDER = ['costs', 'fees', 'default_interest', 'interest', 'principal'];

    public const DEFAULT_TWO_FACTOR_ROLES = [
        'Administrator', 'Vorstand', 'Aufsichtsratsvorsitzender', 'Aufsichtsratsmitglied',
    ];

    public const BUCKET_LABELS = [
        'costs' => 'Kosten',
        'fees' => 'Gebühren',
        'default_interest' => 'Verzugszinsen',
        'interest' => 'Zinsen',
        'principal' => 'Tilgung',
    ];

    public function index(): View
    {
        return view('admin.settings.index', [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'twoFactorRoles' => (array) Setting::get('security', 'two_factor_required_roles', self::DEFAULT_TWO_FACTOR_ROLES),
            'allocationOrder' => (array) Setting::get('loans', 'allocation_order', self::DEFAULT_ALLOCATION_ORDER),
            'maxSizeKb' => (int) Setting::get('documents', 'max_size_kb', config('documents.max_size_kb', 51200)),
            'bucketLabels' => self::BUCKET_LABELS,
        ]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        $old = [
            'two_factor_required_roles' => Setting::get('security', 'two_factor_required_roles'),
            'allocation_order' => Setting::get('loans', 'allocation_order'),
            'max_size_kb' => Setting::get('documents', 'max_size_kb'),
        ];

        $new = [
            'two_factor_required_roles' => array_values($request->input('two_factor_required_roles', [])),
            'allocation_order' => array_values($request->input('allocation_order')),
            'max_size_kb' => $request->integer('max_size_kb'),
        ];

        Setting::set('security', 'two_factor_required_roles', $new['two_factor_required_roles']);
        Setting::set('loans', 'allocation_order', $new['allocation_order']);
        Setting::set('documents', 'max_size_kb', $new['max_size_kb']);

        AuditService::log('admin.settings.updated', null, $old, $new);

        return redirect()->route('admin.settings.index')->with('success', 'Die Einstellungen wurden gespeichert.');
    }
}
