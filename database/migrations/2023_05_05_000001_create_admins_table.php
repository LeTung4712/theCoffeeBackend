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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique(); //Tên đăng nhập
            $table->string('password'); //Mật khẩu
            $table->string('access_token',1000)->nullable(); //Token để lưu trữ thông tin đăng nhập
            $table->string('refresh_token',1000)->nullable(); //Token để lưu trữ thông tin đăng nhập
            $table->timestamp('refresh_token_expired_at')->nullable(); //Thời gian hết hạn của token
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
