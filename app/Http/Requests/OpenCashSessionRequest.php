<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
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
            'opening_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('opening_amount'));

        if (str_contains($amount, ',')) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }

        $this->merge(['opening_amount' => $amount]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'opening_amount.required' => 'Informe o valor inicial do caixa.',
            'opening_amount.numeric' => 'Informe um valor inicial válido.',
            'opening_amount.min' => 'O valor inicial não pode ser negativo.',
        ];
    }
}
