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
        Schema::table('user_transitions', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('transitionable_type')->constrained('roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_transitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
