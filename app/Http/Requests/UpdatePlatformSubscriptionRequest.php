<?php

namespace App\Http\Requests;

use App\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformSubscriptionRequest extends FormRequest
{
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
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'billing_phone' => ['nullable', 'digits_between:10,11'],
            'started_at' => ['required', 'date'],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('monthly_amount'));

        if (str_contains($amount, ',')) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }

        $this->merge([
            'monthly_amount' => $amount,
            'billing_phone' => filled($this->input('billing_phone'))
                ? preg_replace('/\D/', '', (string) $this->input('billing_phone'))
                : null,
            'trial_ends_at' => filled($this->input('trial_ends_at')) ? $this->input('trial_ends_at') : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'monthly_amount.required' => 'Informe o valor mensal.',
            'monthly_amount.numeric' => 'Informe um valor mensal válido.',
            'billing_day.between' => 'Escolha um dia de vencimento entre 1 e 28.',
            'billing_phone.digits_between' => 'Informe um WhatsApp com DDD e 10 ou 11 números.',
            'trial_ends_at.after_or_equal' => 'O fim do teste não pode ser anterior ao início da assinatura.',
        ];
    }
}
