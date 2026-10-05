<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Klaim pelatihan yang SUDAH dilakukan (sertifikat + jam per hari). Beda dari training_requests
 * (pengajuan sebelum pelatihan + approval atasan + ikatan dinas). `jp` baru terisi saat
 * HC/kepegawaian memeriksa konversi jam -> JP, dan hanya klaim approved yang dihitung ke laporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_claims', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('organizer')->nullable();
            $table->string('certificate_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedSmallInteger('jp')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_claim_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_claim_id')->constrained('training_claims')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        $permission = Permission::firstOrCreate(['name' => 'kelola_klaim_pelatihan', 'guard_name' => 'web']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'sistem_lms'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('training_claim_sessions');
        Schema::dropIfExists('training_claims');
    }
};
