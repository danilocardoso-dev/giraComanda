<?php

namespace App\Http\Requests;

use App\ProductSaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->where('company_id', app('currentCompany')->id),
            ],
            'sale_type' => ['required', Rule::enum(ProductSaleType::class)],
            'price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'price' => $this->normalizeCurrency((string) $this->input('price')),
            'stock' => filled($this->input('stock')) ? $this->input('stock') : null,
            'min_stock' => filled($this->input('min_stock')) ? $this->input('min_stock') : null,
            'active' => $this->boolean('active'),
        ]);
    }

    private function normalizeCurrency(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        return $value;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('sale_type') === ProductSaleType::Unit->value && filled($this->input('stock')) && (float) $this->input('stock') !== floor((float) $this->input('stock'))) {
                    $validator->errors()->add('stock', 'Produtos por unidade exigem um estoque inteiro.');
                }

                if ($this->input('sale_type') === ProductSaleType::Unit->value && filled($this->input('min_stock')) && (float) $this->input('min_stock') !== floor((float) $this->input('min_stock'))) {
                    $validator->errors()->add('min_stock', 'Produtos por unidade exigem um estoque mínimo inteiro.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do produto.',
            'name.unique' => 'Já existe um produto com este nome.',
            'sale_type.required' => 'Selecione como o produto é vendido.',
            'sale_type.enum' => 'O tipo de venda selecionado é inválido.',
            'price.required' => 'Informe o preço do produto.',
            'price.numeric' => 'Informe um preço válido.',
            'price.min' => 'O preço deve ser maior que zero.',
            'stock.numeric' => 'Informe um saldo de estoque válido.',
            'stock.min' => 'O estoque não pode ser negativo.',
            'min_stock.numeric' => 'Informe um estoque mínimo válido.',
            'min_stock.min' => 'O estoque mínimo não pode ser negativo.',
        ];
    }
}
