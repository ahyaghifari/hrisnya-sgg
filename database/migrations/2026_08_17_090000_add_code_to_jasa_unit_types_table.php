<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jasa_unit_types', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('jasa_unit_types', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
