<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('m_task_services');
    }

    public function down(): void
    {
        Schema::create('m_task_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
};
