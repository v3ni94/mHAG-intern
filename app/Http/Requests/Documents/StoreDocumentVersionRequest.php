<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('documents.max_size_kb', 51200)],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Datei'];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Bitte wählen Sie eine Datei für die neue Version aus.',
            'file.max' => 'Die Datei überschreitet die maximal zulässige Größe.',
        ];
    }
}
