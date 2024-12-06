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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade'); // khi đơn hàng bị xóa thì tất cả các item của đơn hàng cũng sẽ bị xóa
            $table->foreignId('product_id')->constrained()->onDelete('restrict'); // khi sản phẩm bị xóa thì item vẫn còn
            
            // Lưu thông tin sản phẩm tại thời điểm đặt hàng
            $table->string('product_name', 100);
            $table->decimal('product_price', 10, 2);
            $table->integer('product_quantity')->unsigned();
            
            // Cải thiện lưu trữ topping
            $table->json('topping_items')->nullable()
                  ->comment('Array of {id, name, price}'); //vd: [{"id": 1, "name": "Topping 1", "price": 10000}, {"id": 2, "name": "Topping 2", "price": 20000}]
            
            $table->enum('size', ['S', 'M', 'L']);

            // Thêm trường ghi chú cho từng item
            $table->text('item_note')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            // Thêm indexes
            $table->index(['order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
