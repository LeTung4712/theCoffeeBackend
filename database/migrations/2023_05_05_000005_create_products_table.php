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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); //Tên sản phẩm là duy nhất
            $table->foreignId('category_id')->constrained();
            $table->text('description')->nullable(); //Mô tả
            $table->string('image_url')->nullable(); //Ảnh sản phẩm
            $table->decimal('price', 10, 2); //Giá
            $table->decimal('price_sale', 10, 2)->nullable(); //Giá khuyến mãi
            $table->boolean('active')->default(true); //Trạng thái
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
