<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('announcement_id');
            $table->ulid('user_id');
            $table->text('body');
            $table->timestamps();

            // Satu orang satu komentar per pengumuman.
            $table->unique(['announcement_id', 'user_id']);
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_comments');
    }
};
