<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan klarifikasi: tahap pertama alur SP, sebelum Berita Acara. 1 undangan menghasilkan
 * maksimal 1 BA (sp_reports.invitation_id unique). BA lama tidak punya undangan (nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type')->index();
            $table->foreignUlid('company_id')->constrained('m_companies')->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('location');
            $table->text('note')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'scheduled_at']);
        });

        Schema::table('sp_reports', function (Blueprint $table) {
            $table->foreignUlid('invitation_id')->nullable()->unique()->constrained('invitations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sp_reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('invitation_id'));
        Schema::dropIfExists('invitations');
    }
};
