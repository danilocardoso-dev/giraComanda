<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTabItemShareRequest extends FormRequest
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
            'customer_ids' => ['required', 'array', 'min:2'],
            'customer_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('customers', 'id')->where('active', true),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_ids.required' => 'Selecione os clientes do rateio.',
            'customer_ids.min' => 'Selecione pelo menos dois clientes para dividir o item.',
            'customer_ids.*.exists' => 'Um dos clientes selecionados não está ativo.',
            'customer_ids.*.distinct' => 'Cada cliente pode aparecer somente uma vez no rateio.',
        ];
    }
}
