<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\ProductSaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->where('company_id', app('currentCompany')->id)
                    ->ignore($product),
            ],
            'sale_type' => ['required', Rule::enum(ProductSaleType::class)],
            'price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'price' => $this->normalizeCurrency((string) $this->input('price')),
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
                if ($this->input('sale_type') === ProductSaleType::Unit->value && filled($this->input('min_stock')) && (float) $this->input('min_stock') !== floor((float) $this->input('min_stock'))) {
                    $validator->errors()->add('min_stock', 'Produtos por unidade exigem um estoque mínimo inteiro.');
                }

                /** @var Product $product */
                $product = $this->route('product');

                if ($this->input('sale_type') === ProductSaleType::Unit->value && $product->stock_milliunits !== null && $product->stock_milliunits % 1000 !== 0) {
                    $validator->errors()->add('sale_type', 'Ajuste o estoque para um valor inteiro antes de alterar a venda para unidade.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreProductRequest)->messages();
    }
}
