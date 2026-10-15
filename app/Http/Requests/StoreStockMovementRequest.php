<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\ProductSaleType;
use App\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_map(fn (StockMovementType $type): string => $type->value, StockMovementType::manualCases()))],
            'quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => trim((string) $this->input('description')),
        ]);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['type', 'quantity'])) {
                    return;
                }

                $type = StockMovementType::from($this->string('type')->toString());
                $quantity = $this->float('quantity');

                if ($type !== StockMovementType::Adjustment && $quantity <= 0) {
                    $validator->errors()->add('quantity', 'A quantidade deve ser maior que zero para entradas e saídas.');
                }

                /** @var Product $product */
                $product = $this->route('product');

                if ($product->sale_type === ProductSaleType::Unit && $quantity !== floor($quantity)) {
                    $validator->errors()->add('quantity', 'Produtos por unidade exigem uma quantidade inteira.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => 'Selecione o tipo de movimento.',
            'type.in' => 'Selecione um tipo de movimento manual válido.',
            'quantity.required' => 'Informe a quantidade.',
            'quantity.numeric' => 'Informe uma quantidade válida.',
            'quantity.min' => 'A quantidade não pode ser negativa.',
            'description.required' => 'Informe o motivo ou referência do movimento.',
        ];
    }
}
