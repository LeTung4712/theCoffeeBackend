<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class Users extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'first_name' => 'Thanh Tùng',
                'last_name' => 'Lê',
                'gender' => 'male',
                'date_of_birth' => '2001-08-22',
                'mobile_no' => '+84828035636',
                'email' => 'iamrobotdiy@gmail.com',
                'active' => 1,
                'remember_token' => null,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
        ];
        User::insert($data);
    }
}
