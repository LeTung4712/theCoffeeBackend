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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique(); //mã code đơn hàng
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->string('user_name', 100);
            $table->string('mobile_no', 15);
            $table->enum('status', ['-1', '0', '1', '2', '3'])
                  ->default('0')
                  ->comment('-1: cancelled, 0: pending, 1: paid, 2: shipping, 3: completed');
            $table->string('address');
            $table->text('note')->nullable(); //Ghi chú đơn hàng
            $table->decimal('total_price', 10, 2); //Tổng tiền hàng
            $table->decimal('discount_amount', 10, 2)->default(0); //Số tiền giảm giá
            $table->decimal('shipping_fee', 10, 2)->default(0); //Phí vận chuyển
            $table->decimal('final_price', 10, 2) 
                  ->comment('total_price - discount_amount + shipping_fee'); //Tổng tiền thanh toán
            $table->enum('payment_method', ['cod', 'vnpay', 'momo', 'zalopay'])
                  ->default('cod'); //Phương thức thanh toán
            $table->datetime('order_time'); //Thời gian đặt hàng
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
