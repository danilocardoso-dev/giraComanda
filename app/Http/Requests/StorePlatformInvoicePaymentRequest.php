<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformInvoicePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->platformAdmin()->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(['pix'])],
            'reactivate_access' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'paid_on.required' => 'Informe a data do pagamento.',
            'paid_on.before_or_equal' => 'O pagamento não pode estar no futuro.',
            'payment_method.required' => 'Selecione a forma de pagamento.',
        ];
    }
}
