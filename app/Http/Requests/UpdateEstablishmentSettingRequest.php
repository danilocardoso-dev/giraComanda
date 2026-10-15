<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEstablishmentSettingRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'document' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'general_due_day' => ['required', 'integer', 'between:1,31'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'document' => filled($this->input('document')) ? trim((string) $this->input('document')) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'email' => filled($this->input('email')) ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'address' => filled($this->input('address')) ? trim((string) $this->input('address')) : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do estabelecimento.',
            'logo.image' => 'Envie uma imagem válida para a logo.',
            'logo.mimes' => 'A logo deve estar em PNG, JPG ou WebP.',
            'logo.max' => 'A logo deve ter no máximo 2 MB.',
            'email.email' => 'Informe um e-mail válido.',
            'general_due_day.required' => 'Informe o dia padrão de vencimento.',
            'general_due_day.between' => 'O dia padrão deve estar entre 1 e 31.',
        ];
    }
}
