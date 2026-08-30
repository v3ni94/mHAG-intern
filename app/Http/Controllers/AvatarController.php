<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profilbilder (Anforderung vom 30.08.2026).
 *
 * Grundsätze:
 * - Die Bilder liegen außerhalb des öffentlichen Verzeichnisses. Die Ausgabe
 *   läuft ausschließlich über diesen Controller, damit ein Bild nicht ohne
 *   Anmeldung abrufbar ist (Abschnitt 64).
 * - Nur Rasterbilder: JPEG, PNG und WebP. SVG ist ausgeschlossen, weil eine
 *   SVG-Datei ausführbaren Code enthalten kann (Abschnitt 131).
 * - Der Dateiname wird vom System vergeben, der ursprüngliche Name wird nicht
 *   übernommen.
 * - Beim Austausch und beim Entfernen wird die alte Datei gelöscht; ein
 *   Profilbild ist kein aufbewahrungspflichtiges Dokument.
 */
class AvatarController extends Controller
{
    /** Erlaubte Dateitypen und maximale Größe (2 MB). */
    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_SIZE_KB = 2048;

    /**
     * Bild ausliefern. Erlaubt ist das eigene Bild; fremde Bilder nur für
     * Benutzer mit Benutzerverwaltung.
     */
    public function show(Request $request, int $user): StreamedResponse
    {
        $current = $request->user();
        abort_unless($current->id === $user || $current->can('admin.users'), 403);

        $model = User::withTrashed()->findOrFail($user);
        abort_unless($model->avatar_path && Storage::disk('avatars')->exists($model->avatar_path), 404);

        $mime = match (strtolower(pathinfo($model->avatar_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Storage::disk('avatars')->response($model->avatar_path, 'profilbild', [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline; filename="profilbild"',
        ]);
    }

    /** Eigenes Profilbild hochladen oder austauschen. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'avatar' => [
                    'required',
                    'file',
                    'image',
                    'mimes:'.implode(',', self::ALLOWED_MIMES),
                    'max:'.self::MAX_SIZE_KB,
                    'dimensions:min_width=48,min_height=48,max_width=4000,max_height=4000',
                ],
            ],
            [
                'avatar.required' => 'Bitte wählen Sie eine Bilddatei aus.',
                'avatar.image' => 'Die Datei ist kein Bild.',
                'avatar.mimes' => 'Zulässig sind JPG, PNG und WebP.',
                'avatar.max' => 'Das Bild darf höchstens 2 MB groß sein.',
                'avatar.dimensions' => 'Das Bild muss mindestens 48 mal 48 Punkte groß sein und darf 4000 Punkte je Seite nicht überschreiten.',
            ],
            ['avatar' => 'Profilbild'],
        );

        $user = $request->user();
        $alt = $user->avatar_path;

        $extension = strtolower($validated['avatar']->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, self::ALLOWED_MIMES, true)) {
            $extension = 'jpg';
        }

        // Dateiname vom System vergeben, der Originalname wird nicht uebernommen.
        $name = 'benutzer-'.$user->id.'-'.Str::lower(Str::random(16)).'.'.$extension;
        Storage::disk('avatars')->putFileAs('', $validated['avatar'], $name);

        $user->forceFill(['avatar_path' => $name])->save();

        if ($alt && $alt !== $name) {
            Storage::disk('avatars')->delete($alt);
        }

        AuditService::log('profile.avatar_changed', $user, ['avatar' => $alt ? 'vorhanden' : 'ohne'], ['avatar' => 'vorhanden']);

        return back()->with('success', 'Das Profilbild wurde gespeichert.');
    }

    /** Eigenes Profilbild entfernen; danach erscheint wieder das Firmenzeichen. */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $alt = $user->avatar_path;

        if (! $alt) {
            return back()->with('info', 'Es ist kein Profilbild hinterlegt.');
        }

        Storage::disk('avatars')->delete($alt);
        $user->forceFill(['avatar_path' => null])->save();

        AuditService::log('profile.avatar_removed', $user, ['avatar' => 'vorhanden'], ['avatar' => 'ohne']);

        return back()->with('success', 'Das Profilbild wurde entfernt.');
    }
}
