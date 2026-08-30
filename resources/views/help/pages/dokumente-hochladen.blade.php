<p>
    Dokumente werden zentral verwaltet, mit SHA-256-Prüfsumme gesichert und mit beliebigen Objekten
    (Person, Unternehmen, Darlehen, Vertrag, Beschluss) verknüpft.
</p>

<h2 class="h6 mt-4">Dokument hochladen</h2>
<ol>
    <li>Öffnen Sie <strong>Verträge &amp; Dokumente &rarr; Dokumente</strong> und klicken Sie auf
        <strong>Hochladen</strong>. Alternativ laden Sie direkt im Dokumente-Tab einer Akte hoch.</li>
    <li>Wählen Sie die Datei. Zulässig sind gängige Formate (PDF, JPG, PNG, DOCX, XLSX u. a.);
        ausführbare Dateien werden abgewiesen. Die maximale Dateigröße ist administrierbar.</li>
    <li>Erfassen Sie die Metadaten: Dokumenttyp (z. B. Vertrag, Kontoauszug, Ausweis),
        Dokumentdatum, Beschreibung und optional ein Ablaufdatum.</li>
    <li>Verknüpfen Sie das Dokument mit dem passenden Objekt, z. B. einem Darlehen.</li>
    <li>Speichern Sie. Das System vergibt eine UUID, berechnet die SHA-256-Prüfsumme und legt die
        Datei auf dem konfigurierten Speicher (bevorzugt SFTP) ab.</li>
</ol>

<h2 class="h6 mt-4">Herunterladen und archivieren</h2>
<ul>
    <li>Downloads laufen ausschließlich über die Anwendung mit Berechtigungsprüfung;
        es gibt keine öffentlich erreichbaren Dateipfade.</li>
    <li>Beim Download wird die Prüfsumme verifiziert; Abweichungen werden gemeldet.</li>
    <li>Nicht mehr benötigte Dokumente werden <strong>archiviert</strong> statt gelöscht.
        Endgültiges Löschen ist Administratoren vorbehalten und wird auditiert.</li>
</ul>

<div class="alert alert-info small mt-3 mb-0">
    Neue Versionen eines Dokuments laden Sie als Dokumentversion hoch; die Vorgängerversion bleibt
    erhalten und nachvollziehbar.
</div>
