<?php

namespace App\Http\Requests;

use App\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'required',
                'digits:11',
                new ValidCpf,
                Rule::unique('customers', 'cpf')->where('company_id', app('currentCompany')->id),
            ],
            'phone' => ['nullable', 'digits_between:10,11'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'cpf' => preg_replace('/\\D/', '', (string) $this->input('cpf')),
            'phone' => filled($this->input('phone')) ? preg_replace('/\D/', '', (string) $this->input('phone')) : null,
            'email' => filled($this->input('email')) ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'credit_limit' => filled($this->input('credit_limit')) ? $this->input('credit_limit') : null,
            'due_day' => filled($this->input('due_day')) ? $this->input('due_day') : null,
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do cliente.',
            'cpf.required' => 'Informe o CPF.',
            'cpf.digits' => 'O CPF deve conter 11 números.',
            'cpf.unique' => 'Este CPF já está cadastrado.',
            'phone.digits_between' => 'Informe um telefone com DDD e 10 ou 11 números.',
            'email.email' => 'Informe um e-mail válido.',
            'credit_limit.numeric' => 'O limite de crédito deve ser um valor válido.',
            'credit_limit.min' => 'O limite de crédito não pode ser negativo.',
            'due_day.between' => 'O dia de vencimento deve estar entre 1 e 31.',
        ];
    }
}
