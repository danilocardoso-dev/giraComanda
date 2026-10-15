<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('counted_amount'));

        if (str_contains($amount, ',')) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }

        $this->merge(['counted_amount' => $amount]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'counted_amount.required' => 'Informe o valor contado no caixa.',
            'counted_amount.numeric' => 'Informe um valor contado válido.',
            'counted_amount.min' => 'O valor contado não pode ser negativo.',
        ];
    }
}
