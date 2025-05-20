<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Topping;

class Toppings extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Kem Phô Mai Macchiato',
                'price' => 10000,
                'active' => true, 
                'created_at' => '2021-06-01 00:00:00', 
                'updated_at' => '2021-06-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Shot Espresso',
                'price' => 10000,
                'active' => true, 
                'created_at' => '2021-06-01 00:00:00',
                'updated_at' => '2021-06-01 00:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Sốt Caramel',
                'price' => 10000,
                'active' => true, 
                'created_at' => '2021-06-01 00:00:00',
                'updated_at' => '2021-06-01 00:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Thạch Cà Phê',
                'price' => 10000,
                'active' => true, 
                'created_at' => '2021-06-01 00:00:00',
                'updated_at' => '2021-06-01 00:00:00',
            ],
            [
                'id' => 5,
                'name' => 'Trân Châu Trắng',
                'price' => 10000,
                'active' => true, 
                'created_at' => '2021-06-01 00:00:00',
                'updated_at' => '2021-06-01 00:00:00',
            ],
        ];
        Topping::truncate();
        Topping::insert($data);
    }
}
