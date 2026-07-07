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
        Schema::table('m_doctor_services', function (Blueprint $table) {
            if (Schema::hasColumn('m_doctor_services', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });

        Schema::dropIfExists('m_doctor_service_categories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('m_doctor_service_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('m_doctor_services', function (Blueprint $table) {
            $table->foreignUlid('category_id')
                ->nullable()
                ->constrained('m_doctor_service_categories')
                ->cascadeOnDelete();
        });
    }
};
