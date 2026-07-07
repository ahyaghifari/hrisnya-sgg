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
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('remember_token');
            $table->string('religion')->nullable()->after('gender');
            $table->string('blood_type')->nullable()->after('nickname');
            $table->string('body_height_weight')->nullable()->after('blood_type');
            $table->date('marriage_date')->nullable()->after('body_height_weight');
            $table->string('social_media_accounts')->nullable()->after('marriage_date');
            $table->string('last_education')->nullable()->after('social_media_accounts');
            $table->string('nationality')->nullable()->after('last_education');
        });
        
        Schema::create('candt_family_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('relation');
            $table->string('gender')->nullable();
            $table->string('name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('education')->nullable();
            $table->string('job')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'religion',
                'blood_type',
                'body_height_weight',
                'marriage_date',
                'social_media_accounts',
                'last_education',
                'nationality',
            ]);
        });
        
        Schema::dropIfExists('candt_family_members');
    }
};
