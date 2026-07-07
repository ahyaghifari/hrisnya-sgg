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
        Schema::table('doctor_payrolls', function (Blueprint $table) {
            $table->decimal('gross_salary', 15, 2)->default(0)->after('doctor_payroll_period_id');
            $table->decimal('tax_percentage', 5, 2)->default(0)->after('gross_salary');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_percentage');
            $table->decimal('net_salary', 15, 2)->default(0)->after('tax_amount');
            $table->dropColumn('total_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'gross_salary',
                'tax_percentage',
                'tax_amount',
                'net_salary',
            ]);
            $table->decimal('total_fee', 15, 2)->default(0);
        });
    }
};
