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
            $table->unsignedBigInteger('user_id')->nullable();  // ستون user_id را اضافه می‌کند
            $table->unsignedBigInteger('article_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');  // کلید خارجی به جدول users
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropForeign(['user_id']);  // حذف کلید خارجی
            $table->dropColumn('user_id');     // حذف ستون
            $table->dropForeign(['article_id']);
            $table->dropColumn('article_id');
        });
    }

};
