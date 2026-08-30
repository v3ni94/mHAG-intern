<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Intranet') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/logo-mhag.jpg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
<div class="mhag-shell">
    <aside class="mhag-sidebar" id="sidebar">
        <div class="brand">
            <img src="{{ asset('images/logo-mhag.jpg') }}" alt="Logo Müller Holding AG">
            <div>
                <div class="name">Müller Holding AG</div>
                <div class="sub">Intranet</div>
            </div>
        </div>
        <nav class="mhag-nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            @canany(['loans.view', 'payments.view'])
                <div class="nav-section">Finanzen</div>
                @can('loans.view')
                    <a href="{{ route('loans.index') }}" class="{{ request()->routeIs('loans.*') ? 'active' : '' }}">
                        <i class="bi bi-cash-stack"></i> Darlehen
                    </a>
                @endcan
                @can('payments.view')
                    <a href="{{ route('payments.index') }}" class="{{ request()->routeIs('payments.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-left-right"></i> Zahlungen
                    </a>
                    <a href="{{ route('due-items.index') }}" class="{{ request()->routeIs('due-items.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-check"></i> Fälligkeiten
                    </a>
                @endcan
                @can('loans.view')
                    <a href="{{ route('securities.index') }}" class="{{ request()->routeIs('securities.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-check"></i> Sicherheiten
                    </a>
                    <a href="{{ route('liquidity.index') }}" class="{{ request()->routeIs('liquidity.*') ? 'active' : '' }}">
                        <i class="bi bi-graph-up"></i> Liquidität
                    </a>
                @endcan
            @endcanany

            @canany(['persons.view', 'companies.view'])
                <div class="nav-section">Stammdaten</div>
                @can('persons.view')
                    <a href="{{ route('persons.index') }}" class="{{ request()->routeIs('persons.*') ? 'active' : '' }}">
                        <i class="bi bi-person"></i> Personen
                    </a>
                @endcan
                @can('companies.view')
                    <a href="{{ route('companies.index') }}" class="{{ request()->routeIs('companies.*') ? 'active' : '' }}">
                        <i class="bi bi-building"></i> Unternehmen
                    </a>
                @endcan
            @endcanany

            @canany(['contracts.view', 'documents.view'])
                <div class="nav-section">Verträge &amp; Dokumente</div>
                @can('contracts.view')
                    <a href="{{ route('contracts.index') }}" class="{{ request()->routeIs('contracts.*', 'contract-templates.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text"></i> Verträge
                    </a>
                @endcan
                @can('documents.view')
                    <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'active' : '' }}">
                        <i class="bi bi-folder2-open"></i> Dokumente
                    </a>
                @endcan
            @endcanany

            @canany(['shares.view', 'resolutions.view'])
                <div class="nav-section">Müller Holding AG</div>
                @can('shares.view')
                    <a href="{{ route('holding.dashboard') }}" class="{{ request()->routeIs('holding.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3"></i> Holding-Dashboard
                    </a>
                    <a href="{{ route('shareholders.index') }}" class="{{ request()->routeIs('shareholders.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i> Aktionäre
                    </a>
                    <a href="{{ route('share-transactions.index') }}" class="{{ request()->routeIs('share-transactions.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-repeat"></i> Aktienbewegungen
                    </a>
                    <a href="{{ route('investments.index') }}" class="{{ request()->routeIs('investments.*') ? 'active' : '' }}">
                        <i class="bi bi-pie-chart"></i> Beteiligungen
                    </a>
                    <a href="{{ route('corporate-bodies.index') }}" class="{{ request()->routeIs('corporate-bodies.*') ? 'active' : '' }}">
                        <i class="bi bi-person-badge"></i> Vorstand &amp; Aufsichtsrat
                    </a>
                @endcan
                @can('resolutions.view')
                    <a href="{{ route('resolutions.index') }}" class="{{ request()->routeIs('resolutions.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-check"></i> Beschlüsse
                    </a>
                    <a href="{{ route('signatures.index') }}" class="{{ request()->routeIs('signatures.*') ? 'active' : '' }}">
                        <i class="bi bi-pen"></i> Unterschriften
                    </a>
                @endcan
            @endcanany

            <div class="nav-section">Organisation</div>
            <a href="{{ route('calendar.index') }}" class="{{ request()->routeIs('calendar.*') ? 'active' : '' }}">
                <i class="bi bi-calendar3"></i> Kalender
            </a>
            <a href="{{ route('reminders.index') }}" class="{{ request()->routeIs('reminders.*') ? 'active' : '' }}">
                <i class="bi bi-bell"></i> Wiedervorlagen
            </a>
            @can('reports.view')
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-data"></i> Reports
                </a>
            @endcan
            <a href="{{ route('help.index') }}" class="{{ request()->routeIs('help.*', 'faq.*') ? 'active' : '' }}">
                <i class="bi bi-question-circle"></i> Hilfe &amp; FAQ
            </a>

            @can('admin.settings')
                <div class="nav-section">Administration</div>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*', 'admin.invitations.*') ? 'active' : '' }}">
                    <i class="bi bi-person-gear"></i> Benutzer
                </a>
                <a href="{{ route('admin.roles.index') }}" class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                    <i class="bi bi-key"></i> Rollen
                </a>
                <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders"></i> Einstellungen
                </a>
                <a href="{{ route('admin.sftp.index') }}" class="{{ request()->routeIs('admin.sftp.*') ? 'active' : '' }}">
                    <i class="bi bi-hdd-network"></i> SFTP
                </a>
                <a href="{{ route('admin.backups.index') }}" class="{{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">
                    <i class="bi bi-archive"></i> Backups
                </a>
                <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                    <i class="bi bi-list-check"></i> Audit-Log
                </a>
                <a href="{{ route('admin.status') }}" class="{{ request()->routeIs('admin.status') ? 'active' : '' }}">
                    <i class="bi bi-heart-pulse"></i> Systemstatus
                </a>
            @endcan
        </nav>
    </aside>
    <div class="mhag-backdrop" id="sidebarBackdrop"></div>

    <div class="mhag-main">
        <header class="mhag-topbar">
            <button class="btn btn-outline-secondary d-lg-none btn-sm" id="sidebarToggle" aria-label="Navigation öffnen">
                <i class="bi bi-list"></i>
            </button>

            <form action="{{ route('search.index') }}" method="GET" class="global-search" role="search">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Suche: Name, Darlehensnummer, IBAN, Beschluss ..." aria-label="Globale Suche">
                </div>
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                @php($contexts = auth()->user()?->entityAssignments ?? collect())
                @if ($contexts->count() > 1)
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-arrow-left-right"></i>
                            <span class="d-none d-md-inline">Ansicht: {{ auth()->user()->currentContext()?->label ?: auth()->user()->currentContext()?->entity?->display_name ?: 'Standard' }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach ($contexts as $assignment)
                                <li>
                                    <form method="POST" action="{{ route('context.switch') }}">
                                        @csrf
                                        <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                                        <button class="dropdown-item {{ auth()->user()->currentContext()?->id === $assignment->id ? 'fw-bold' : '' }}">
                                            {{ $assignment->label ?: $assignment->entity?->display_name }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.privacy') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" title="Datenschutzmodus: Geldbeträge {{ auth()->user()?->privacy_mode ? 'einblenden' : 'ausblenden' }}">
                        <i class="bi {{ auth()->user()?->privacy_mode ? 'bi-eye-slash-fill' : 'bi-eye' }}"></i>
                    </button>
                </form>

                @include('partials.notification-bell')

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-md-inline">{{ auth()->user()?->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="{{ route('two-factor.setup') }}"><i class="bi bi-shield-lock me-2"></i>Zwei-Faktor-Authentifizierung</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="mhag-content">
            @include('partials.flash')
            @yield('content')
        </main>

        <footer class="mhag-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-bold">Müller Holding AG</span> · Rheinpromenade 13 · 40789 Monheim am Rhein · kontakt@mueller-holding.ag · mueller-holding.ag<br>
                Sitz: Monheim am Rhein · Registergericht: Amtsgericht Düsseldorf · HRB 104291 · Vorstand: Timo Müller · Aufsichtsratsvorsitzender: Jan Walprecht
            </div>
            <div class="text-end">
                @include('partials.daily-fact')
            </div>
        </footer>
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
    (function () {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggle = document.getElementById('sidebarToggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                backdrop.classList.toggle('show');
            });
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('open');
                backdrop.classList.remove('show');
            });
        }
    })();

    // Kontextbezogene Hilfe (Abschnitt 112) und Feldtooltips (Abschnitt 117):
    // Bootstrap-Popovers und -Tooltips müssen ausdrücklich initialisiert werden,
    // sonst bleibt der hinterlegte Hilfetext unsichtbar.
    (function () {
        if (typeof bootstrap === 'undefined') {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            if (!bootstrap.Popover.getInstance(el)) {
                new bootstrap.Popover(el, { container: 'body', html: false });
            }
        });
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el, { container: 'body' });
            }
        });
    })();
</script>
@stack('scripts')
</body>
</html>
