<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_filter_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
            $table->enum('grantee_type', ['role', 'user']);
            $table->string('grantee_id'); // role.id (bigint-as-string) atau user.id (ulid)
            $table->json('systems'); // array nilai App\Enum\CustomPermissionEnum::SISTEM_*
            $table->timestamps();

            $table->unique(['company_id', 'grantee_type', 'grantee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_filter_access');
    }
};
