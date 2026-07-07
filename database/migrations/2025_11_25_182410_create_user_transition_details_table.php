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
        Schema::create('user_transition_details', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_transition_id')
                ->constrained('user_transitions')
                ->cascadeOnDelete();

            $table->string('target_achievement')->nullable();
            $table->text('target_comment')->nullable();
            $table->string('work_quality')->nullable();
            $table->text('work_quality_comment')->nullable();
            $table->string('productivity_level')->nullable();
            $table->text('productivity_comment')->nullable();

            $table->string('responsibility_adherence')->nullable();
            $table->text('responsibility_comment')->nullable();
            $table->string('work_ethic')->nullable();
            $table->text('work_ethic_comment')->nullable();
            $table->string('teamwork')->nullable();
            $table->text('teamwork_comment')->nullable();

            $table->text('external_factors')->nullable();
            $table->text('employee_request')->nullable();
            $table->text('other_comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_transition_details');
    }
};
