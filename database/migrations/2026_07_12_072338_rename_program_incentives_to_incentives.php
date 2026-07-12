<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('program_incentives', 'incentives');

        Schema::table('incentives', function (Blueprint $table) {
            $table->renameColumn('program_name', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->renameColumn('name', 'program_name');
        });

        Schema::rename('incentives', 'program_incentives');
    }
};
