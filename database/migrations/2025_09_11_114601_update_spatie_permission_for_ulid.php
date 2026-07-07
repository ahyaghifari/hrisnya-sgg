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
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['permission_id']);
            $table->dropIndex(['model_id', 'model_type']);
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropIndex(['model_id', 'model_type']);
        });

        // ganti tipe kolom model_id jadi ULID
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->char('model_id', 26)->change();
            $table->index(['model_id', 'model_type']);
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->char('model_id', 26)->change();
            $table->index(['model_id', 'model_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex(['model_id', 'model_type']);
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex(['model_id', 'model_type']);
        });

        // kembalikan tipe kolom model_id ke integer
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')
                ->references('id') // permission id
                ->on('permissions')
                ->onDelete('cascade');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id')->change();
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')
                ->references('id') // role id
                ->on('roles')
                ->onDelete('cascade');
        });
    }
};
