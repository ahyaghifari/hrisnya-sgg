<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger Merit Point (planning/2026-09-23-sp-dan-merit-point.md §3.2) - masih berstatus DEMO.
 * `points` snapshot (tidak ikut berubah walau rule-nya diedit belakangan). Ledger tidak pernah
 * diedit - sengaja tanpa updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_point_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('rule_id')->nullable()->constrained('merit_point_rules')->restrictOnDelete();
            $table->integer('points');
            $table->text('reason');
            $table->string('source_type')->nullable();
            $table->ulid('source_id')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_point_transactions');
    }
};
