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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('order_code', 30)->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cod', 'vnpay', 'momo', 'zalopay']);
            $table->enum('status', ['0', '1', '2', '3'])
                ->default('0')
                ->comment('0: pending, 1: completed, 2: failed, 3: cancelled');
            $table->string('transaction_id', 100)->nullable();
            $table->text('payment_gateway_response')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('transaction_id');
            $table->index('order_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
