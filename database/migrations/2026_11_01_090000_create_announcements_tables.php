<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->nullable()->index();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('published_by')->nullable();
            $table->timestamps();
        });

        // Target = daftar jabatan akhir (hasil centang pohon perusahaan > departemen > divisi >
        // jabatan di form admin) - pohonnya sendiri tidak disimpan, dibangun ulang dari master.
        Schema::create('announcement_job_position', function (Blueprint $table) {
            $table->ulid('announcement_id');
            $table->ulid('job_position_id');
            $table->primary(['announcement_id', 'job_position_id']);
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_job_position');
        Schema::dropIfExists('announcements');
    }
};
