<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use App\Models\Voucher;

class Vouchers extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'code' => 'tungboss2',
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1698765382_coupon-20k.jpg',
                'description' => 'Giảm  20.000 Đơn từ 60.000',
                'discount_type' => 'amount',
                'discount_percent' => null,
                'max_discount_amount' => 20000.00,
                'min_order_amount' => 60000.00,
                'expire_at' => '2026-01-01',
                'total_quantity' => 100,
                'used_quantity' => 0,
                'active' => 1,
                'limit_per_user' => 10,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'code' => 'tungboss',
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1698765189_40-fs.jpg',
                'description' => 'Giảm 40% tối đa 25.000 Đơn từ 150.000',
                'discount_type' => 'percent',
                'discount_percent' => 40.00,
                'max_discount_amount' => 25000.00,
                'min_order_amount' => 150000.00,
                'expire_at' => '2026-01-01',
                'total_quantity' => 100,
                'used_quantity' => 0,
                'active' => 1,
                'limit_per_user' => 10,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 3,  
                'code' => 'tungboss1',
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1698765397_coupon-30k.jpg',
                'description' => 'Giảm  30.000 Đơn từ 99.000',
                'discount_type' => 'amount',
                'discount_percent' => null,
                'max_discount_amount' => 30000.00,
                'min_order_amount' => 99000.00,
                'expire_at' => '2026-01-01',
                'total_quantity' => 100,
                'used_quantity' => 0,
                'active' => 1,
                'limit_per_user' => 10,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 4,
                'code' => 'tungboss3',
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1709222265_deli-copy-7.jpg',
                'description' => 'Giảm 30% tối đa 25.000 Đơn từ 100.000',
                'discount_type' => 'percent',
                'discount_percent' => 30.00,
                'max_discount_amount' => 25000.00,
                'min_order_amount' => 100000.00,
                'expire_at' => '2026-01-01',
                'total_quantity' => 100,
                'used_quantity' => 0,
                'active' => 1,
                'limit_per_user' => 10,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ]
        ];
        Voucher::insert($data);
    }
}
