<?php

namespace App\Http\Requests\Holding;

/**
 * Upload der signierten Fassung (Abschnitt 100, Schritte 6 bis 9).
 */
class AttachSignedDocumentRequest extends HoldingFormRequest
{
    public function rules(): array
    {
        return [
            'signed_file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ];
    }

    public function attributes(): array
    {
        return ['signed_file' => 'Signiertes PDF'];
    }
}
