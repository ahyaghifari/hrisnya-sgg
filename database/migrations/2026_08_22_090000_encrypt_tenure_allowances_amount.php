<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kelewat waktu migration 2026_08_13_180000 - amount tenure_allowances belum ikut dienkripsi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenure_allowances', function (Blueprint $table) {
            $table->text('amount')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenure_allowances', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });
    }
};
