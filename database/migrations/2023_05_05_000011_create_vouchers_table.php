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
            $table->string("code", 20)->unique(); 
            $table->string("image_url", 255);
            $table->string("description", 100);
            $table->enum("discount_type", ["percent", "amount"])->notnull();
            $table->decimal("discount_percent", 5, 2)->nullable();  
            $table->decimal("max_discount_amount", 10, 2);
            $table->decimal("min_order_amount", 10, 2);
            $table->date("expire_at");
            $table->integer("total_quantity")->notnull();
            $table->integer("used_quantity")->default(0);
            $table->boolean("active")->default(true);
            $table->integer("limit_per_user")->default(1);
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
