<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('topping_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade'); // khi sản phẩm bị xóa thì topping sẽ bị xóa
            $table->json('topping_id')
                  ->comment('Array of topping ids'); //vd: [1, 2, 3] 
            $table->timestamps();
            $table->unique(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topping_products');
    }
};
