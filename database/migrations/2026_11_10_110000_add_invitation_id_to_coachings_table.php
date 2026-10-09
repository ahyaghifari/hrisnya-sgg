<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coaching yang lahir dari undangan coaching: 1 undangan = maksimal 1 Coaching (unik). Nullable, karena
 * Coaching tetap boleh dibuat tanpa undangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coachings', function (Blueprint $table) {
            $table->foreignUlid('invitation_id')->nullable()->unique()->constrained('invitations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coachings', fn (Blueprint $table) => $table->dropConstrainedForeignId('invitation_id'));
    }
};