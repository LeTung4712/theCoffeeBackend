<?php
namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            Admins::class,
            Users::class,
            Address::class,
            Toppings::class,
            Categories::class,
            Products::class,
            Vouchers::class,
            ToppingProducts::class,
        ]);
    }
}
