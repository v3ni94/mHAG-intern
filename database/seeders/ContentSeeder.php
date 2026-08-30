<?php

namespace Database\Seeders;

use App\Models\ChangelogEntry;
use App\Models\ContractTemplate;
use App\Models\FaqEntry;
use Illuminate\Database\Seeder;

/**
 * Redaktionelle Inhalte: FAQ (Abschnitt 113), Changelog (Abschnitt 118)
 * und eine Beispiel-Vertragsvorlage mit Platzhaltern (Abschnitt 53).
 * daily_facts wird bewusst NICHT befüllt: keine erfundenen Aktionstage
 * (Abschnitt 119), die Tabelle wird redaktionell gepflegt.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['Darlehen', 'Kann ich ein Darlehen rückwirkend anlegen?', "Ja. Vertragsbeginn (Wirkungsdatum) und Erfassungsdatum werden getrennt gespeichert. Das System rekonstruiert den historischen Verlauf ab dem Wirkungsbeginn automatisch: Auszahlungen, Zinsen, Tilgungen, Gebühren und Forderungsstände.", 'all'],
            ['Darlehen', 'Was passiert, wenn ich keine Zahlung erfasse?', 'Bei entsprechendem Vertragsmodell wird planmäßige Erfüllung zunächst systemseitig angenommen und ausdrücklich als solche gekennzeichnet (blauer Status "Systemseitig angenommen"). Eine systemseitig angenommene Zahlung ist keine bestätigte Bankzahlung.', 'all'],
            ['Darlehen', 'Was mache ich, wenn Zinsen nicht gezahlt wurden?', 'Öffnen Sie im Darlehen den Zahlungsplan, wählen Sie die betroffene Periode und setzen Sie den Status auf "Nicht bezahlt" (Istbetrag 0,00 EUR). Das System berechnet automatisch alle Folgewerte neu: offene Zinsen, Forderungsstand, Jahreswerte, Dashboard und Reports.', 'all'],
            ['Darlehen', 'Kann ich Teilzahlungen erfassen?', 'Ja. Sollbetrag, tatsächlicher Betrag und offener Rest werden getrennt gespeichert. Die Periode erhält den Status "Teilweise bezahlt".', 'all'],
            ['Darlehen', 'Was passiert bei ausgefallener Tilgung?', 'Das offene Kapital bleibt höher. Zinsen und alle Folgewerte werden ab dem betroffenen Datum automatisch neu berechnet.', 'all'],
            ['Beschlüsse', 'Kann ich frühere Beschlüsse erfassen?', 'Ja. Das tatsächliche Beschlussdatum und das technische Erfassungsdatum werden getrennt gespeichert. Der Audit-Trail wird nicht rückdatiert.', 'internal'],
            ['Grundlagen', 'Kann eine Person mehrere Rollen haben?', 'Ja. Eine Person kann gleichzeitig z. B. Aufsichtsratsmitglied, Darlehensgeber, Darlehensnehmer und Aktionär sein. Über den Kontextwechsel oben rechts wird die Ansicht gewechselt.', 'all'],
            ['Grundlagen', 'Wo liegen die Dokumente?', 'Metadaten liegen in der Datenbank (MariaDB), die Dateien auf dem konfigurierten Storage, bevorzugt dem SFTP-Server. Downloads laufen ausschließlich über die Anwendung mit Berechtigungsprüfung.', 'all'],
            ['Grundlagen', 'Was bedeutet "Wirkungsdatum" und "Erfassungsdatum"?', 'Das Wirkungsdatum ist das Datum, ab dem ein Vorgang fachlich gilt. Das Erfassungsdatum ist das Datum, an dem der Vorgang technisch im System eingetragen wurde. Beide bleiben im Audit-Trail erhalten.', 'all'],
        ];

        foreach ($faqs as $i => [$category, $question, $answer, $visibility]) {
            FaqEntry::firstOrCreate(
                ['question' => $question],
                [
                    'category' => $category,
                    'answer' => $answer,
                    'sort' => $i + 1,
                    'visibility' => $visibility,
                    'is_active' => true,
                ],
            );
        }

        ChangelogEntry::firstOrCreate(
            ['version' => '1.0.0'],
            [
                'released_on' => now()->toDateString(),
                'changes' => "**Erstveröffentlichung des Intranets der Müller Holding AG.**\n\n"
                    ."Neue Funktionen:\n"
                    ."- Personen- und Unternehmensverwaltung mit zentralem Geschäftspartnerstamm\n"
                    ."- Darlehensverwaltung mit Soll/Ist-Logik, Zahlungsplan und Neuberechnungs-Engine\n"
                    ."- Zahlungen, Auszahlungen, Gebühren, Sicherheiten und Bürgschaften\n"
                    ."- Vertragsvorlagen mit Versionierung und PDF-Erzeugung\n"
                    ."- Dokumentenmanagement mit SFTP-Ablage und Integritätsprüfung (SHA-256)\n"
                    ."- Aktionärsverwaltung, Aktienbewegungen und stichtagsfähige Aktionärslisten\n"
                    ."- Beteiligungen, Vorstand, Aufsichtsrat, Beschlussverwaltung und Signaturen\n"
                    ."- Dashboards, Reports, Liquiditätsplanung, Kalender und Wiedervorlagen\n"
                    ."- Benutzerverwaltung mit Rollen, Einladungen und Zwei-Faktor-Authentifizierung\n"
                    ."- Audit-Trail, Backups und Systemstatus",
            ],
        );

        // Beispiel-Vertragsvorlage (Entwurf, Abschnitt 52/53). Inhalt ist eine
        // neutrale Struktur ohne rechtliche Bewertung; die fachliche und
        // rechtliche Prüfung obliegt dem Verwender.
        $template = ContractTemplate::firstOrCreate(
            ['name' => 'Privatdarlehen (Basisvorlage)'],
            [
                'category' => 'Privatdarlehen',
                'description' => 'Neutrale Basisvorlage für ein Privatdarlehen. Vor Verwendung rechtlich prüfen lassen. Entwurfskennzeichnung bis zur Finalisierung.',
                'is_active' => true,
            ],
        );

        if ($template->versions()->count() === 0) {
            $template->versions()->create([
                'version' => '1.0',
                'body' => <<<'HTML'
<h1>Darlehensvertrag</h1>
<p><strong>Darlehensnummer:</strong> {{darlehensnummer}}</p>

<p>zwischen</p>
<p><strong>{{darlehensgeber.name}}</strong><br>{{darlehensgeber.anschrift}}<br>(nachfolgend „Darlehensgeber")</p>
<p>und</p>
<p><strong>{{darlehensnehmer.name}}</strong><br>{{darlehensnehmer.anschrift}}<br>(nachfolgend „Darlehensnehmer")</p>

<h2>§ 1 Darlehensbetrag und Auszahlung</h2>
<p>Der Darlehensgeber gewährt dem Darlehensnehmer ein Darlehen in Höhe von <strong>{{darlehensbetrag}}</strong>.
Die Auszahlung erfolgt zum {{auszahlungstag}} auf das vom Darlehensnehmer benannte Konto.</p>

<h2>§ 2 Verzinsung</h2>
<p>Das Darlehen wird ab dem {{beginn}} mit <strong>{{zinssatz}}</strong> p. a. verzinst.
Die Zinsen sind {{zinsfaelligkeit}} fällig. Zinsmethode: {{zinsmethode}}.</p>

<h2>§ 3 Laufzeit und Rückzahlung</h2>
<p>Die Laufzeit beginnt am {{beginn}} und endet am {{ende}}. Die Rückzahlung erfolgt {{tilgungsregelung}}.
Fälligkeit: {{faelligkeit}}.</p>

<h2>§ 4 Sicherheiten</h2>
<p>{{sicherheit}}</p>

<h2>§ 5 Schlussbestimmungen</h2>
<p>Änderungen und Ergänzungen dieses Vertrages bedürfen der Textform. Sollten einzelne Bestimmungen unwirksam sein,
bleibt die Wirksamkeit des Vertrages im Übrigen unberührt.</p>

<table style="width:100%; margin-top:60px;">
  <tr>
    <td style="width:50%;">______________________________<br>Ort, Datum, Unterschrift Darlehensgeber</td>
    <td style="width:50%;">______________________________<br>Ort, Datum, Unterschrift Darlehensnehmer</td>
  </tr>
</table>
HTML,
                'placeholders' => [
                    'darlehensnummer', 'darlehensgeber.name', 'darlehensgeber.anschrift',
                    'darlehensnehmer.name', 'darlehensnehmer.anschrift', 'darlehensbetrag',
                    'auszahlungstag', 'beginn', 'ende', 'zinssatz', 'zinsfaelligkeit',
                    'zinsmethode', 'tilgungsregelung', 'faelligkeit', 'sicherheit',
                ],
            ]);
        }
    }
}
