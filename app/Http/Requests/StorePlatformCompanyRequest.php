<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePlatformCompanyRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'branch_name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner_password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_name' => trim((string) $this->input('company_name')),
            'branch_name' => trim((string) $this->input('branch_name')),
            'owner_name' => trim((string) $this->input('owner_name')),
            'owner_email' => mb_strtolower(trim((string) $this->input('owner_email'))),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Informe o nome do estabelecimento.',
            'branch_name.required' => 'Informe o nome da primeira filial.',
            'owner_name.required' => 'Informe o nome do proprietário.',
            'owner_email.required' => 'Informe o e-mail do proprietário.',
            'owner_email.email' => 'Informe um e-mail válido para o proprietário.',
            'owner_email.unique' => 'Este e-mail já está cadastrado no sistema.',
            'owner_password.required' => 'Informe uma senha inicial.',
            'owner_password.confirmed' => 'A confirmação da senha não corresponde.',
            'owner_password.min' => 'A senha inicial deve ter pelo menos 8 caracteres.',
        ];
    }
}
