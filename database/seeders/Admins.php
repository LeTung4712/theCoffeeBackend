<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class Admins extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'username' => 'admin@gmail.com',
                'password' => Hash::make('123456'),
                'access_token' => null,
                'refresh_token' => null,
                'refresh_token_expired_at' => null,
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00',
            ],
        ];
        Admin::truncate();
        Admin::insert($data);
    }
}
