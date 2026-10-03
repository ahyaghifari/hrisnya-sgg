<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/HRIS_Alur_Penandatanganan_Multi_Signature.md: FIX/LOCK (fixed_at), FINAL (finalized_at,
 * final_hash dari payload, file_path/file_hash = PDF final tersimpan yang tidak pernah dirender
 * ulang) dan audit trail (document_events).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('fixed_at')->nullable()->after('issued_at');
            $table->timestamp('finalized_at')->nullable()->after('fixed_at');
            $table->string('final_hash', 64)->nullable()->after('finalized_at');
            $table->string('file_path')->nullable()->after('final_hash');
            $table->string('file_hash', 64)->nullable()->after('file_path');
        });

        // Dokumen lama: terbit = sudah FIX. Yang sudah lengkap TTD-nya difinalkan lewat sign() berikutnya/tidak perlu backfill.
        DB::table('documents')->update(['fixed_at' => DB::raw('issued_at')]);

        Schema::create('document_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('event');
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_events');
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn(['fixed_at', 'finalized_at', 'final_hash', 'file_path', 'file_hash']));
    }
};
