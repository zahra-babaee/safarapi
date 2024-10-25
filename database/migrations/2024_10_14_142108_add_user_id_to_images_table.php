<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('images', function (Blueprint $table) {
            // Add user_id and article_id columns
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('article_id')->nullable();

            // Add foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            // Check if the foreign keys exist before dropping
            if (Schema::hasColumn('images', 'user_id')) {
                $table->dropForeign(['user_id']);  // Drop foreign key
                $table->dropColumn('user_id');     // Drop column
            }
            if (Schema::hasColumn('images', 'article_id')) {
                $table->dropForeign(['article_id']); // Drop foreign key
                $table->dropColumn('article_id');     // Drop column
            }
        });
    }
};
