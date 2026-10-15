<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\ProductSaleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTabItemRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists('branch_products', 'product_id')
                    ->where('branch_id', app('currentBranch')->id)
                    ->where('active', true),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['product_id', 'quantity'])) {
                    return;
                }

                $product = Product::query()->find($this->integer('product_id'));

                if ($product?->sale_type === ProductSaleType::Unit && (float) $this->input('quantity') !== floor((float) $this->input('quantity'))) {
                    $validator->errors()->add('quantity', 'Produtos por unidade exigem uma quantidade inteira.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Selecione um produto.',
            'product_id.exists' => 'O produto selecionado não está disponível.',
            'quantity.required' => 'Informe a quantidade ou o peso.',
            'quantity.gt' => 'A quantidade deve ser maior que zero.',
            'quantity.decimal' => 'Informe no máximo três casas decimais.',
        ];
    }
}
