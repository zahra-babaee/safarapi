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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['avatar', 'article'])->default('avatar');
            $table->string('path');
            $table->boolean('is_default')->default(false); //False:ندارد - true:دارد
//            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
//            $table->foreignId('article_id')->nullable()->constrained('articles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
