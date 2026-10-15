<?php

namespace App\Http\Requests;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformInvoiceRequest extends FormRequest
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
        /** @var Company $company */
        $company = $this->route('company');

        return [
            'reference_month' => [
                'required',
                'date',
                Rule::unique('subscription_invoices', 'reference_month')
                    ->where('subscription_id', $company->subscription?->id),
            ],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('amount'));
        $referenceMonth = (string) $this->input('reference_month');

        if (str_contains($amount, ',')) {
            $amount = str_replace(',', '.', str_replace('.', '', $amount));
        }

        $this->merge([
            'amount' => $amount,
            'reference_month' => preg_match('/^\d{4}-\d{2}$/', $referenceMonth) === 1
                ? Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth()->toDateString()
                : $referenceMonth,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_month.required' => 'Informe o mês da mensalidade.',
            'reference_month.unique' => 'Já existe uma mensalidade para este mês.',
            'due_date.required' => 'Informe o vencimento.',
            'amount.required' => 'Informe o valor da mensalidade.',
            'amount.min' => 'O valor da mensalidade deve ser maior que zero.',
        ];
    }
}
