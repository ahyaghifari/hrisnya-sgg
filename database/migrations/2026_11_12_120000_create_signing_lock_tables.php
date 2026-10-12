<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatasan percobaan password saat menandatangani dokumen (TTE). Status per akun:
 *  - 5 password salah beruntun (jendela 15 menit) -> blokir sementara 15 menit;
 *  - 3 blokir sementara dalam 24 jam -> blokir keras, hanya dibuka pemegang permission
 *    buka_blokir_tanda_tangan (bukan pemilik akunnya sendiri).
 * Blokir hanya untuk fungsi tanda tangan, login tetap bisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_locks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->unsignedSmallInteger('temp_lock_count')->default(0);
            $table->timestamp('temp_lock_window_started_at')->nullable();
            $table->timestamp('hard_locked_at')->nullable();
            $table->timestamps();
        });

        // Jejak audit blokir/buka blokir (append-only): berlaku level akun, jadi terpisah dari
        // document_events (yang selalu menempel ke 1 dokumen).
        Schema::create('signing_lock_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event'); // failed | locked_temp | locked_hard | unlocked
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_lock_events');
        Schema::dropIfExists('signing_locks');
    }
};
