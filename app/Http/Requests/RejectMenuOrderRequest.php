<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectMenuOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['rejection_reason' => trim((string) $this->input('rejection_reason'))]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Informe o motivo da recusa para o cliente.',
            'rejection_reason.max' => 'O motivo pode ter no máximo 255 caracteres.',
        ];
    }
}
