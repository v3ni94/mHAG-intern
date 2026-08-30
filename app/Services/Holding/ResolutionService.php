<?php

namespace App\Services\Holding;

use App\Enums\ResolutionType;
use App\Enums\VoteChoice;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Resolution;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use App\Services\Storage\DocumentStorageInterface;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Beschlussverwaltung (Abschnitte 88 bis 98 Masterprompt):
 * Nummernkreise je Beschlussart, CI-PDF, Abstimmungszählung.
 */
class ResolutionService
{
    public function __construct(
        private readonly DocumentStorageInterface $storage,
    ) {
    }

    /** Nächste Beschlussnummer, z. B. VOR-2026-001 (Abschnitt 92). */
    public function nextNumber(ResolutionType $type): string
    {
        return NumberSequenceService::next($type->numberPrefix(), 3);
    }

    /**
     * Beschluss-PDF im CI (Abschnitt 97) erzeugen, in der Dokumentenablage
     * unter gesellschaft/beschluesse speichern und mit dem Beschluss verknüpfen.
     */
    public function generatePdf(Resolution $resolution): Document
    {
        $resolution->loadMissing([
            'company.company',
            'applicant',
            'participants.entity',
            'participants.vote',
            'links.linkable',
        ]);

        $pdfContent = Pdf::loadView('resolutions.pdf.resolution', [
            'resolution' => $resolution,
            'summary' => $this->voteSummary($resolution),
        ])->output();

        $document = $this->storage->store(
            $pdfContent,
            'gesellschaft/beschluesse',
            $resolution->resolution_number.'.pdf',
            [
                'doc_type' => 'resolution',
                'category' => 'Beschluss',
                'document_date' => $resolution->resolved_on?->toDateString(),
                'description' => sprintf('Beschluss %s: %s', $resolution->resolution_number, $resolution->title),
            ],
        );

        $resolution->document_id = $document->id;
        $resolution->save();

        DocumentLink::firstOrCreate([
            'document_id' => $document->id,
            'linkable_type' => $resolution->getMorphClass(),
            'linkable_id' => $resolution->id,
        ]);

        AuditService::log(
            'resolutions.pdf-generated',
            $resolution,
            [],
            ['document_id' => $document->id, 'sha256' => $document->sha256],
        );

        return $document;
    }

    /**
     * Reine Zählung der Stimmen (Abschnitt 94). Das System bewertet
     * keine gesetzlichen Mehrheiten und leitet kein Ergebnis ab.
     */
    public function voteSummary(Resolution $resolution): array
    {
        $summary = ['yes' => 0, 'no' => 0, 'abstain' => 0, 'absent' => 0];

        $resolution->loadMissing('votes');

        foreach ($resolution->votes as $vote) {
            $choice = $vote->vote;
            if ($choice instanceof VoteChoice) {
                $summary[$choice->value]++;
            }
        }

        return $summary;
    }
}
