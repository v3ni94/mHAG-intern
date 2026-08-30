<?php

namespace App\Http\Requests\Documents;

use App\Http\Controllers\DocumentController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('documents.max_size_kb', 51200)],
            'doc_type' => ['required', Rule::in(array_keys(DocumentController::DOC_TYPES))],
            'category' => ['nullable', 'string', 'max:120'],
            'document_date' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'link_type' => ['nullable', Rule::in(array_keys(DocumentController::LINKABLE_TYPES))],
            'link_id' => ['nullable', 'required_with:link_type', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Datei',
            'doc_type' => 'Dokumenttyp',
            'category' => 'Kategorie',
            'document_date' => 'Dokumentdatum',
            'expires_on' => 'Ablaufdatum',
            'description' => 'Beschreibung',
            'tags' => 'Tags',
            'link_type' => 'Verknüpfungsart',
            'link_id' => 'Verknüpfungsziel',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Bitte wählen Sie eine Datei aus.',
            'file.max' => 'Die Datei überschreitet die maximal zulässige Größe.',
            'doc_type.required' => 'Bitte wählen Sie einen Dokumenttyp aus.',
            'doc_type.in' => 'Der gewählte Dokumenttyp ist nicht zulässig.',
            'link_type.in' => 'Die gewählte Verknüpfungsart ist nicht zulässig.',
            'link_id.required_with' => 'Bitte geben Sie das Verknüpfungsziel an.',
        ];
    }

    /** Tags als Array (Komma-getrennt eingegeben). */
    public function tagsArray(): ?array
    {
        $raw = trim((string) $this->input('tags'));
        if ($raw === '') {
            return null;
        }

        $tags = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== ''));

        return $tags === [] ? null : $tags;
    }
}
