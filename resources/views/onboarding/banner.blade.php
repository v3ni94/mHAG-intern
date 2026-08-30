{{-- Erste-Schritte-Assistent (Abschnitt 111): Hinweisstreifen für Administratoren,
     solange der Assistent offen ist. Überspringen blendet ihn dauerhaft aus. --}}
@if (! request()->routeIs('onboarding.*') && \App\Http\Controllers\OnboardingController::bannerVisible(auth()->user()))
    <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <i class="bi bi-flag"></i>
            <strong>Erste Schritte:</strong>
            Der Assistent führt in zehn Schritten durch die Einrichtung und zeigt den Erledigungsstand.
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('onboarding.index') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-arrow-right-circle"></i> Assistent öffnen
            </a>
            <form method="POST" action="{{ route('onboarding.skip') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">Überspringen</button>
            </form>
        </div>
    </div>
@endif
