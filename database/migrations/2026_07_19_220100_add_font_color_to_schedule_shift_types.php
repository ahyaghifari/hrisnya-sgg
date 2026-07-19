<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Warna teks per kode shift (dipasangkan sama `color` = warna background) — admin pilih
     * sendiri kontrasnya, bukan ditebak otomatis (mis. selalu putih) dari kode. */
    public function up(): void
    {
        Schema::table('schedule_shift_types', function (Blueprint $table) {
            $table->string('font_color')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_shift_types', function (Blueprint $table) {
            $table->dropColumn('font_color');
        });
    }
};
