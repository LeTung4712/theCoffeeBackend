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
        Schema::create('address_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // khi user bị xóa thì tất cả các địa chỉ của user cũng sẽ bị xóa
            $table->string('user_name');                                      //Tên người nhận
            $table->string('address');                                        //Địa chỉ
            $table->string('mobile_no');                                      //Số điện thoại
            $table->string('address_type')->default('home');                  //Loại địa chỉ
            $table->boolean('is_default')->default(false);                    //Địa chỉ mặc định
            $table->string('place_id');                                      //Mã địa điểm
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('address_notes');
    }
};
