<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_respondents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('nip')->nullable();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_respondents');
    }
};
