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

        Schema::create('association_rules', function (Blueprint $table) {
            $table->id();
            $table->string('antecedent'); //Tiền đề
            $table->string('consequent'); //Kết luận
            $table->decimal('confidence', 5, 4); //Độ tin cậy
            $table->decimal('lift', 5, 4)->nullable(); //Chỉ số Lift 
            $table->decimal('support', 5, 4)->nullable(); //Chỉ số Support
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('association_rules');
    }
};
