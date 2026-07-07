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
        Schema::create('candidates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Auth
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Basic Profile
            $table->string('gender')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('nik')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status_marital')->nullable();

            // Additional profile fields
            $table->text('ktp_address')->nullable();
            $table->text('domicile_address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();

            $table->string('mother_name')->nullable();
            $table->string('education')->nullable();
            $table->string('major')->nullable();
            $table->string('university')->nullable();
            $table->year('graduation_year')->nullable();

            $table->string('no_bpjskes')->nullable();
            $table->string('faskes')->nullable();
            $table->string('no_bpjstk')->nullable();
            $table->string('company_bpjstk')->nullable();
            $table->string('bsi_bank_number')->nullable();
            $table->text('profile_picture')->nullable();
            // Candidate status
            $table->boolean('is_onboarded')->default(false);
            $table->string('overall_status')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
