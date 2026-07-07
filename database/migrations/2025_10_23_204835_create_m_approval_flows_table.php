<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /*
        |--------------------------------------------------------------------------
        | MASTER APPROVAL FLOW
        |--------------------------------------------------------------------------
        | Menyimpan daftar template flow untuk tiap modul & company
        |--------------------------------------------------------------------------
        */
    public function up(): void
    {
        Schema::create('m_approval_flows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('module');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_approval_flows');
    }
};
