<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bedakan Extra Shift (ada jasa - amount dari ExtraShiftRule) dari Double Shift (tidak ada
     * jasa - akibat tukar shift yang conflict, bukan penugasan sukarela) - lihat
     * ShiftSwapRequestService::applySlotResolution() mode 'double_shift'. Payroll nanti (belum
     * diwire) harus skip baris category='double_shift' dari perhitungan jasa extra shift.
     */
    public function up(): void
    {
        Schema::table('extra_shift_assignments', function (Blueprint $table) {
            $table->string('category')->default('extra_shift')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('extra_shift_assignments', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
