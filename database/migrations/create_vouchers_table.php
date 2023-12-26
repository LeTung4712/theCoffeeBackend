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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string("code", 55)->unique(); 
            $table->string("image_url", 255);
            $table->string("description", 100);
            $table->enum("discount_type", ["percent", "amount"])->notnull();
            $table->integer("discount_percent")->nullable();  
            $table->integer("max_discount_amount");
            $table->integer("min_order_amount");
            $table->date("expire_at");
            $table->integer("total_quantity")->notnull();
            $table->integer("used_quantity")->default(0);
            $table->integer("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
