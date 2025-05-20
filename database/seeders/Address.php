<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use App\Models\AddressNote;

class Address extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'user_id' => 1,
                'user_name' => 'Lê Thanh Tùng',
                'address' => '409 tam trinh',
                'mobile_no' => '+84828035636',
                'address_type' => 'home',
                'is_default' => 1,
                'province_code' => '1',
                'district_code' => '1',
                'ward_code' => '1',
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'user_name' => 'Lê Thanh Tùng',
                'address' => '33 Lĩnh Nam',
                'mobile_no' => '+84828035636',
                'address_type' => 'home',
                'is_default' => 0,
                'province_code' => '1',
                'district_code' => '1',
                'ward_code' => '1',
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],  
            [
                'id' => 3,
                'user_id' => 1,
                'user_name' => 'Lê Thanh Tùng',
                'address' => '34 Hai Bà Trưng',
                'mobile_no' => '+84828035636',
                'address_type' => 'home',
                'is_default' => 0,
                'province_code' => '1',
                'district_code' => '1',
                'ward_code' => '1',
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
        ];
        AddressNote::truncate();
        AddressNote::insert($data);
    }
}
