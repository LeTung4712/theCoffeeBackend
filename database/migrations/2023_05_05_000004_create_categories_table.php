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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); //Tên danh mục
            $table->foreignId('parent_id')->nullable()->constrained('categories'); //Danh mục cha
            $table->string('image_url')->nullable(); //Ảnh danh mục
            $table->boolean('active')->default(true); //Trạng thái
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
