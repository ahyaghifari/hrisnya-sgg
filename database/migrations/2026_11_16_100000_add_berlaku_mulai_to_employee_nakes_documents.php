<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_nakes_documents', function (Blueprint $table) {
            $table->date('berlaku_mulai')->nullable()->after('nomor');
        });
    }

    public function down(): void
    {
        Schema::table('employee_nakes_documents', fn (Blueprint $table) => $table->dropColumn('berlaku_mulai'));
    }
};
