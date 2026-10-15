<?php

namespace App\Http\Requests;

use App\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CloseTabRequest extends FormRequest
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
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments' => [Rule::requiredIf($this->input('payment_method') === PaymentMethod::Mixed->value), 'nullable', 'array:cash,pix,debit_card,credit_card'],
            'payments.cash' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payments.pix' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payments.debit_card' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payments.credit_card' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'customer_id' => [
                Rule::requiredIf($this->input('payment_method') === PaymentMethod::Account->value),
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('active', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payments = [];

        foreach (PaymentMethod::immediateCases() as $method) {
            $value = $this->input("payments.{$method->value}");
            $payments[$method->value] = filled($value) ? $this->normalizeCurrency((string) $value) : null;
        }

        $this->merge(['payments' => $payments]);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('payment_method') !== PaymentMethod::Mixed->value || $validator->errors()->hasAny(['payment_method', 'payments'])) {
                    return;
                }

                $amounts = collect($this->input('payments', []))
                    ->filter(fn ($amount): bool => filled($amount))
                    ->map(fn ($amount): int => (int) round((float) $amount * 100));

                if ($amounts->count() < 2) {
                    $validator->errors()->add('payments', 'Informe pelo menos duas formas de pagamento.');

                    return;
                }

                if ($amounts->sum() !== $this->route('tab')->total_cents) {
                    $validator->errors()->add('payments', 'A soma dos pagamentos deve ser igual ao total da comanda.');
                }
            },
        ];
    }

    private function normalizeCurrency(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        return $value;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Selecione a forma de fechamento.',
            'payment_method.enum' => 'A forma de fechamento selecionada é inválida.',
            'payments.required' => 'Informe os valores do pagamento misto.',
            'payments.*.numeric' => 'Informe valores válidos para o pagamento misto.',
            'payments.*.min' => 'Cada valor informado deve ser maior que zero.',
            'customer_id.required' => 'Selecione o cliente que receberá a anotação.',
            'customer_id.exists' => 'O cliente selecionado não está ativo.',
        ];
    }
}
