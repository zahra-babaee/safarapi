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
        Schema::table('otps', function (Blueprint $table) {
            $table->enum('type', ['register', 'forget', 'update', 'old'])->change();
        });
    }

    public function down()
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->enum('type', ['register', 'forget'])->change();
        });
    }
};
