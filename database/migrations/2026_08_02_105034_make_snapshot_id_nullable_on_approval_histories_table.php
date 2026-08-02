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
        Schema::table('approval_histories', function (Blueprint $table) {
            $table->dropForeign(['snapshot_id']);
        });

        Schema::table('approval_histories', function (Blueprint $table) {
            $table->foreignUlid('snapshot_id')->nullable()->change();
        });

        Schema::table('approval_histories', function (Blueprint $table) {
            $table->foreign('snapshot_id')->references('id')->on('approval_snapshots')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_histories', function (Blueprint $table) {
            $table->dropForeign(['snapshot_id']);
        });

        Schema::table('approval_histories', function (Blueprint $table) {
            $table->foreignUlid('snapshot_id')->nullable(false)->change();
        });

        Schema::table('approval_histories', function (Blueprint $table) {
            $table->foreign('snapshot_id')->references('id')->on('approval_snapshots')->cascadeOnDelete();
        });
    }
};
