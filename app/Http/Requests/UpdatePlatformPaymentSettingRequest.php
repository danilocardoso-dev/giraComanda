<?php

namespace App\Http\Requests;

use App\Models\PlatformPaymentSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlatformPaymentSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->active && $this->user()->platformAdmin()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'environment' => ['required', Rule::in(array_keys(PlatformPaymentSetting::ENVIRONMENTS))],
            'enabled' => ['sometimes', 'boolean'],
        ];

        foreach (PlatformPaymentSetting::CREDENTIAL_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:4096', 'not_regex:/\s/', Rule::prohibitedIf($this->boolean('remove_'.$field))];
            $rules['remove_'.$field] = ['sometimes', 'boolean'];
        }

        foreach (array_keys(PlatformPaymentSetting::ENVIRONMENTS) as $environment) {
            $rules[$environment.'_plan_id'] = ['nullable', 'string', 'max:100', 'alpha_dash:ascii'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('enabled')) {
                return;
            }
            $settings = PlatformPaymentSetting::query()->where('provider', PlatformPaymentSetting::PROVIDER)->first();
            foreach (['access_token', 'webhook_secret'] as $key) {
                $field = $this->input('environment').'_'.$key;
                if ($this->boolean('remove_'.$field) || (blank($this->input($field)) && blank($settings?->getAttribute($field)))) {
                    $validator->errors()->add($field, 'Cadastre esta credencial antes de habilitar os pagamentos.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'environment.required' => 'Escolha o ambiente da integração.',
            'environment.in' => 'Escolha Testes ou Produção.',
            '*.string' => 'Informe um texto válido.',
            '*.max' => 'O conteúdo informado ultrapassa o tamanho permitido.',
            '*.not_regex' => 'Cole somente a chave, sem espaços ou quebras de linha.',
            '*.prohibited' => 'Escolha entre substituir a credencial ou removê-la.',
            '*.boolean' => 'A opção de remoção é inválida.',
            '*.alpha_dash' => 'Informe apenas o ID do plano, sem o link de pagamento.',
        ];
    }
}
