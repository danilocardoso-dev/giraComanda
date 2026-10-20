<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTrialRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'billing_phone' => ['required', 'digits_between:10,11'],
            'owner_password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_name' => trim((string) $this->input('company_name')),
            'owner_name' => trim((string) $this->input('owner_name')),
            'owner_email' => mb_strtolower(trim((string) $this->input('owner_email'))),
            'billing_phone' => preg_replace('/\D/', '', (string) $this->input('billing_phone')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Informe o nome do estabelecimento.',
            'owner_name.required' => 'Informe seu nome.',
            'owner_email.required' => 'Informe seu e-mail.',
            'owner_email.email' => 'Informe um e-mail válido.',
            'owner_email.unique' => 'Este e-mail já possui acesso ao GiraComanda.',
            'billing_phone.required' => 'Informe seu WhatsApp.',
            'billing_phone.digits_between' => 'Informe um WhatsApp com DDD e 10 ou 11 números.',
            'owner_password.required' => 'Crie uma senha de acesso.',
            'owner_password.confirmed' => 'A confirmação da senha não corresponde.',
            'owner_password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'terms.accepted' => 'Você precisa aceitar os termos para iniciar o teste.',
        ];
    }
}
