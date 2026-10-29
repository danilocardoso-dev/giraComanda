<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuOrderRequest extends FormRequest
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
            'customer_name' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => filled($this->input('customer_name')) ? trim((string) $this->input('customer_name')) : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Escolha pelo menos um item do cardápio.',
            'items.min' => 'Escolha pelo menos um item do cardápio.',
            'items.max' => 'O pedido pode ter no máximo 30 itens diferentes.',
            'items.*.quantity.min' => 'A quantidade mínima de cada item é 1.',
            'items.*.quantity.max' => 'A quantidade máxima de cada item é 20.',
            'customer_name.max' => 'O nome pode ter no máximo 80 caracteres.',
            'notes.max' => 'A observação pode ter no máximo 500 caracteres.',
        ];
    }
}
