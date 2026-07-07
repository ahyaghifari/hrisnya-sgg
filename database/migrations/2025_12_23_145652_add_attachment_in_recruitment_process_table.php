<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recruitment_processes', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('notes');
            $table->text('feedback')->nullable()->after('attachments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_processes', function (Blueprint $table) {
            $table->dropColumn('attachments');
            $table->dropColumn('feedback');
        });
    }
};
