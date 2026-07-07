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
        Schema::table('trainers', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->enum('type', ['internal', 'external'])->default('external')->after('id');
        });

        Schema::create('trainer_score_variables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name'); 
            $table->text('description')->nullable();
            $table->enum('type', ['honor', 'validity']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('type');
        });
        
        Schema::dropIfExists('trainer_score_variables');
    }
};
