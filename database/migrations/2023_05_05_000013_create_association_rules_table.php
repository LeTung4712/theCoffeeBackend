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
