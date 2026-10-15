<?php

namespace App\Http\Requests;

use App\CardStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTabRequest extends FormRequest
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
            'card_id' => [
                'required',
                'integer',
                Rule::exists('cards', 'id')->where('status', CardStatus::Available->value),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'card_id.required' => 'Selecione um cartão disponível.',
            'card_id.exists' => 'O cartão selecionado não está disponível.',
        ];
    }
}
