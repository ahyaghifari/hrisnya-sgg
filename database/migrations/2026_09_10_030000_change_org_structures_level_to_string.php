<?php

use App\Enum\OrgStructurLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_org_structures', function (Blueprint $table) {
            $table->string('level', 20)->default(OrgStructurLevel::LEVEL_DEPARTMENT->value)->change();
        });

        DB::table('m_org_structures')->where('level', '1')->update(['level' => OrgStructurLevel::LEVEL_DEPARTMENT->value]);
        DB::table('m_org_structures')->where('level', '2')->update(['level' => OrgStructurLevel::LEVEL_DIVISION->value]);
        DB::table('m_org_structures')->where('level', '3')->update(['level' => OrgStructurLevel::LEVEL_SUBDIVISION->value]);
    }

    public function down(): void
    {
        DB::table('m_org_structures')->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)->update(['level' => '1']);
        DB::table('m_org_structures')->where('level', OrgStructurLevel::LEVEL_DIVISION->value)->update(['level' => '2']);
        DB::table('m_org_structures')->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value)->update(['level' => '3']);

        Schema::table('m_org_structures', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->change();
        });
    }
};
