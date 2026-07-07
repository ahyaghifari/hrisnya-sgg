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
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->date('execution_date')->nullable()->after('score');
            $table->text('notes')->nullable()->after('execution_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropColumn(['execution_date', 'notes']);
        });
    }
};
