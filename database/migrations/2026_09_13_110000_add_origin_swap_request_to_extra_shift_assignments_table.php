<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris category=double_shift (lihat migration add_category_to_extra_shift_assignments)
     * perlu tau dia berasal dari pengajuan tukar shift MANA - instruksi user 2026-09-13, biar
     * bisa ditelusuri baliknya (mis. dari halaman detail Extra Shift, klik ke tukar shift
     * asalnya). Null buat baris extra_shift biasa (bukan hasil tukar shift).
     */
    public function up(): void
    {
        Schema::table('extra_shift_assignments', function (Blueprint $table) {
            $table->foreignUlid('origin_shift_swap_request_id')
                ->nullable()
                ->after('category')
                ->constrained('shift_swap_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extra_shift_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_shift_swap_request_id');
        });
    }
};
