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
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->integer('sort_order')->after('id')->nullable();
        });

        Schema::table('lms_materials', function (Blueprint $table) {
            $table->integer('sort_order')->after('id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_meetings', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('lms_materials', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
