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
            $table->date('date_of_birth')->nullable(); //Ngày sinh
            $table->string('mobile_no', 15); //Số điện thoại
            $table->string('email', 100); //Email
            $table->boolean('active')->default(true); //Trạng thái
            $table->rememberToken(); //Token để lưu trữ thông tin đăng nhập
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
