<?php

namespace App\Http\Requests;

use App\CardStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCardRequest extends FormRequest
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
            'status' => ['required', Rule::in([CardStatus::Available->value, CardStatus::Blocked->value])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('card')->status === CardStatus::InUse) {
                    $validator->errors()->add('status', 'Feche a comanda antes de alterar este cartão.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Selecione a situação do cartão.',
            'status.enum' => 'A situação selecionada é inválida.',
            'status.in' => 'A situação selecionada é inválida.',
        ];
    }
}
