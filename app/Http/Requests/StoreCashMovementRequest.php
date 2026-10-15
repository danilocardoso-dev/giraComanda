<?php

namespace App\Http\Requests;

use App\CashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
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
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'description' => ['required', 'string', 'max:255'],
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
            'type.required' => 'Selecione o tipo de movimento.',
            'type.enum' => 'Selecione um tipo de movimento válido.',
            'amount.required' => 'Informe o valor do movimento.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'description.required' => 'Informe o motivo do movimento.',
        ];
    }
}
