<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cards' => ['required', 'array', 'min:1', 'max:50'],
            'cards.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cards.required' => 'Selecione pelo menos um cartão para imprimir.',
            'cards.array' => 'A seleção de cartões é inválida.',
            'cards.min' => 'Selecione pelo menos um cartão para imprimir.',
            'cards.max' => 'Selecione no máximo 50 cartões por impressão.',
            'cards.*.integer' => 'A seleção contém um cartão inválido.',
            'cards.*.distinct' => 'A seleção contém cartões repetidos.',
        ];
    }
}
