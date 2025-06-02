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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->enum('gender', ['male', 'female', 'other']); //Giới tính
            $table->date('date_of_birth')->nullable();           //Ngày sinh
            $table->string('mobile_no', 15);                     //Số điện thoại
            $table->string('email', 100);                        //Email
            $table->string('access_token',1000)->nullable();             //Token JWT
            $table->string('refresh_token',1000)->nullable(); //Token để lưu trữ thông tin đăng nhập
            $table->timestamp('refresh_token_expired_at')->nullable(); //Thời gian hết hạn của token
            $table->timestamp('last_login_at')->nullable();      //Thời gian đăng nhập cuối
            $table->integer('login_attempts')->default(0);       //Số lần đăng nhập sai
            $table->timestamp('locked_until')->nullable();       //Thời gian khóa tài khoản
            $table->boolean('is_active')->default(true);         //Trạng thái hoạt động
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
