<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perubahan config generik (Setting, toggle Komponen Gaji, Mapping Komponen Gaji
     * dkk) — dicatat otomatis lewat trait App\Models\Concerns\LogsAuditChanges di model terkait,
     * bukan ditulis manual per halaman. `key` unik per field/baris dalam 1 company (dipakai
     * langsung buat scope query riwayat 1 field, lihat App\Models\AuditLog::record()).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module');
            $table->string('key');
            $table->string('label')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
