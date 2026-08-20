<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop tabel d_* lama: kosong, tidak direferensikan di kode manapun, digantikan
 * oleh tabel user_documents (kolom document_type pakai App\Enum\UserDocumentType).
 */
return new class extends Migration
{
    private array $tables = [
        'd_appointment_letters',
        'd_assignment_letters',
        'd_employee_resigns',
        'd_employee_retirements',
        'd_employee_transfers',
        'd_warning_letters',
        'd_work_certificates',
        'd_work_contracts',
        'd_working_experience_letters',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void {}
};
