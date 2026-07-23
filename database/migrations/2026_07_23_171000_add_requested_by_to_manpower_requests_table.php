<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `manpower_requests` tidak pernah menyimpan siapa yang mengajukan (beda dari UserTransition
 * yang punya `created_by`) — dibutuhkan supaya ApprovalTimelineService (dipakai generik lintas
 * modul, butuh relasi `user()`) bisa menampilkan "Diajukan oleh" di portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manpower_requests', function (Blueprint $table) {
            $table->foreignUlid('requested_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manpower_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn('requested_by');
        });
    }
};
