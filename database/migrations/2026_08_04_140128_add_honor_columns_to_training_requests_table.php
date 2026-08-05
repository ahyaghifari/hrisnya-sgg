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
        Schema::table('training_requests', function (Blueprint $table) {
            $table->unsignedInteger('total_score')->default(0)->after('estimated_cost');
            $table->decimal('honor_amount', 15, 2)->nullable()->after('total_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_requests', function (Blueprint $table) {
            $table->dropColumn(['total_score', 'honor_amount']);
        });
    }
};
