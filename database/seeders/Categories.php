<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class Categories extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Cà Phê',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1678778483_ca-phe-uong-lien.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 2,
                'name' => 'CloudFee',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724526_cloudfee-new.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 3,
                'name' => 'ClouldTea',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724535_cloudtea.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 4,
                'name' => 'Trà',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724564_tra-trai-cay.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 5,
                'name' => 'Hi-Tea Heathy',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724550_hi-tea.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 6,
                'name' => 'Bánh & Snack',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724490_banh.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 7,
                'name' => 'Tại Nhà',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724504_ca-phe-uong-lien.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 8,
                'name' => 'Thức uống khác',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1677724557_thuc-uong-khac.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00'
            ],
            [
                'id' => 9,
                'name' => 'Cà Phê Việt Nam',
                'parent_id' => 1,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 10,
                'name' => 'Cà Phê Máy',
                'parent_id' => 1,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 11,
                'name' => 'Trà trái cây',
                'parent_id' => 4,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 12,
                'name' => 'Trà sữa Macchiato',
                'parent_id' => 4,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 13,
                'name' => 'Bánh mặn',
                'parent_id' => 6,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 14,
                'name' => 'Snack',
                'parent_id' => 6,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 15,
                'name' => 'Cold Brew',
                'parent_id' => 1,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 16,
                'name' => 'Bánh ngọt',
                'parent_id' => 6,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 17,
                'name' => 'Cà phê tại nhà',
                'parent_id' => 7,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 18,
                'name' => 'Trà tại nhà',
                'parent_id' => 7,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 19,
                'name' => 'Chocolate',
                'parent_id' => 8,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 20,
                'name' => 'Hi-Tea Trà',
                'parent_id' => 5,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 21,
                'name' => 'Hi-Tea Đá Tuyết',
                'parent_id' => 5,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 22,
                'name' => 'Matcha',
                'parent_id' => null,
                'image_url' => 'https://minio.thecoffeehouse.com/image/admin/1736415059_traxanh1.png',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ],
            [
                'id' => 23,
                'name' => 'Matcha Tây Bắc',
                'parent_id' => 22,
                'image_url' => '',
                'active' => true,
                'created_at' => '2023-09-01 00:00:00',
                'updated_at' => '2023-09-01 00:00:00' 
            ]   
        ];  
        //Category::truncate(); 
        Category::insert($data);
    }
}
