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
        Schema::create('user_transition_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_transition_id')
                ->constrained('user_transitions')
                ->cascadeOnDelete();
            $table->string('file_path');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_transition_attachments');
    }
};
