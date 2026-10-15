<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'required',
                'digits:11',
                new ValidCpf,
                Rule::unique('customers', 'cpf')
                    ->where('company_id', app('currentCompany')->id)
                    ->ignore($customer),
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
        return (new StoreCustomerRequest)->messages();
    }
}
