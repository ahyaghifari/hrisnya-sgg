<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa yang membuat coaching — bisa beda dari coach (coach_by). Baris lama tidak diketahui
 * pembuatnya, jadi nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coachings', function (Blueprint $table) {
            $table->foreignUlid('created_by')->nullable()->after('coach_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coachings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
