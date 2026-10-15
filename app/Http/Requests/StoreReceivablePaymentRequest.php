<?php

namespace App\Http\Requests;

use App\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivablePaymentRequest extends FormRequest
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
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::immediateCases()))],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('amount'));

        if (str_contains($amount, ',')) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }

        $this->merge(['amount' => $amount]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'method.required' => 'Selecione a forma de recebimento.',
            'method.in' => 'Selecione uma forma de recebimento válida.',
            'amount.required' => 'Informe o valor recebido.',
            'amount.numeric' => 'Informe um valor recebido válido.',
            'amount.min' => 'O valor recebido deve ser maior que zero.',
        ];
    }
}
