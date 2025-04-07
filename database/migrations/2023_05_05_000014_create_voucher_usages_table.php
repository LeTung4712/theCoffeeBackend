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
        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->onDelete('restrict'); // khi voucher bị xóa thì voucher usage vẫn còn   
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // khi user bị xóa thì tất cả các voucher usage của user cũng sẽ bị xóa
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_usages');
    }
};
