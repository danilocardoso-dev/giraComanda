<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\TabItem;
use App\Models\TabItemShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabItemShare>
 */
class TabItemShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tab_item_id' => TabItem::factory(),
            'customer_id' => Customer::factory(),
            'amount_cents' => 500,
        ];
    }
}
