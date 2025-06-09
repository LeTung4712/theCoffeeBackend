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
                'address' => '409 Tam Trinh, Hoàng Văn Thụ, Hoàng Mai, Hà Nội',
                'place_id' => 'opm4R7EounVwvWIkjnOgyECgYVSRcL2TQJI_Kad-18wGi2ZAuGHuyxStVx6mB7eSdtZIP3QHgZavn0grXFyR7pmnRg2fBJJwkExHU5FhkTfh0i1QHpweBkXajXHCRLOrR',
                'mobile_no' => '0828035636',
                'address_type' => 'home',
                'is_default' => 1,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'user_name' => 'Lê Thanh Tùng',
                'address' => 'Ngõ 409 Tam Trinh, Phường Hoàng Văn Thụ, Quận Hoàng Mai, Thành phố Hà Nội',
                'place_id' => '3W-HlKZB3GRllrQbsnub3klBeTWu3b7qcoEMLapFIdB53JCIn3daaH-CxRadDYCbf99vIp95mFVxfV0Khrmqa57pmdxyueL2dfLhOWphomPF9gl0Org6ImH-qVSWYD-PY',
                'mobile_no' => '0828035636',
                'address_type' => 'home',
                'is_default' => 0,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],  
            [
                'id' => 3,
                'user_id' => 1,
                'user_name' => 'Lê Thanh Tùng',
                'address' => '34 Hai Bà Trưng, Hà Nội',
                'place_id' => '3W-HlKZB3GRllrQbsnub3klBeTWu3b7qcoEMLapFIdB53JCIn3daaH-CxRadDYCbf99vIp95mFVxfV0Khrmqa57pmdxyueL2dfLhOWphomPF9gl0Org6ImH-qVSWYD-PY',
                'mobile_no' => '0828035636',
                'address_type' => 'home',
                'is_default' => 0,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
        ];
        AddressNote::truncate();
        AddressNote::insert($data);
    }
}
