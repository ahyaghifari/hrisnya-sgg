<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris item permohonan — inti fitur persetujuan sebagian: Direktur bisa setuju penuh, setuju
 * sebagian jumlah (jumlah_disetujui < jumlah), atau tolak, per baris. Status kepala dokumen
 * (sarpras_requests.status) dihitung ulang dari status seluruh item di sini, bukan ditulis
 * manual di tiap aksi — lihat SarprasRequest::computeStatus().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_request_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sarpras_request_id')->constrained()->cascadeOnDelete();

            $table->string('nama');
            $table->text('spesifikasi')->nullable();
            $table->unsignedInteger('jumlah')->default(1);
            $table->string('satuan')->default('unit');
            $table->decimal('harga_satuan', 15, 2)->nullable();

            $table->string('status')->default('menunggu');
            $table->unsignedInteger('jumlah_disetujui')->nullable();
            $table->text('catatan_direktur')->nullable();
            $table->timestamp('diputuskan_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_request_items');
    }
};
