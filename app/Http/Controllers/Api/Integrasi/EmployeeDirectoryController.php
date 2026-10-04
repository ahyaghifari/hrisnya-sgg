<?php

namespace App\Http\Controllers\Api\Integrasi;

use App\Enum\OrgStructurLevel;
use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Integrasi\IntegrationApp;
use App\Models\Integrasi\IntegrationAppLog;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direktori karyawan (nama/email/unit) by NIP — dipakai sistem lain buat isi data akun yang
 * di-auto-provision dari SSO (lihat packages/syifa/keycloak-sso, config('user.fill')). Sumber
 * kebenaran data karyawan TETAP HRIS — sistem lain tidak boleh ambil nama/email dari klaim
 * Keycloak (bisa usang/beda ejaan), harus dari sini.
 *
 * Auth: Bearer token per aplikasi (App\Models\Integrasi\IntegrationApp) — dikelola lewat
 * panel /keycloak/integration-apps, token bisa di-generate ulang kapan saja tanpa deploy
 * ulang. PII — beda dari /publik/unit-organisasi yang publik tanpa auth (itu cuma nama+kode
 * unit, bukan data karyawan).
 *
 * Tiap panggilan ke sini dicatat ke IntegrationAppLog (termasuk yang gagal auth) — lihat
 * panel /keycloak menu "Log Integrasi" buat pastikan sistem client BENERAN menjangkau HRIS.
 */
class EmployeeDirectoryController extends Controller
{
    public function show(Request $request, string $nip)
    {
        $app = $this->authenticate($request);

        if ($app instanceof JsonResponse) {
            $this->log(null, $nip, 'unauthorized', $request);

            return $app;
        }

        $app->recordUsage();

        $user = User::whereNip($nip)->first();

        if (! $user) {
            $this->log($app, $nip, 'not_found', $request);

            return ApiResponseHelper::notFound('Karyawan dengan NIP tersebut tidak ditemukan.');
        }

        $this->log($app, $nip, 'success', $request);

        return ApiResponseHelper::success([
            'nip' => $user->nip,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'company' => $user->company?->name,
            'org_unit' => $this->orgUnitCodes($user),
        ]);
    }

    /**
     * Kode Departemen/Divisi/Sub Divisi tempat karyawan ditempatkan (jabatan aktif) — naik dari
     * node org_structure jabatan sampai ke root, isi kode per level yang dilewati. Node yang
     * belum diisi 'code' (lihat DepartmentsResource dkk) balik null, bukan dilewati diam-diam,
     * supaya pemanggil tau kodenya belum didaftarkan.
     *
     * @return array{department: ?string, division: ?string, sub_division: ?string}
     */
    private function orgUnitCodes(User $user): array
    {
        $codes = ['department' => null, 'division' => null, 'sub_division' => null];

        $node = $user->activeJobPosition?->jobPosition?->orgStructure;

        while ($node instanceof OrgStructure) {
            $key = match ($node->level) {
                OrgStructurLevel::LEVEL_DEPARTMENT->value => 'department',
                OrgStructurLevel::LEVEL_DIVISION->value => 'division',
                OrgStructurLevel::LEVEL_SUBDIVISION->value => 'sub_division',
                default => null,
            };

            if ($key) {
                $codes[$key] = $node->code;
            }

            $node = $node->parent;
        }

        return $codes;
    }

    /** @return IntegrationApp|JsonResponse aplikasi pemanggil, atau respons 401 */
    private function authenticate(Request $request): IntegrationApp|JsonResponse
    {
        $token = $request->bearerToken();
        $app = $token ? IntegrationApp::findByToken($token) : null;

        if (! $app) {
            return response()->json(
                ['success' => false, 'message' => 'Unauthorized.', 'errors' => null],
                401,
            );
        }

        return $app;
    }

    private function log(?IntegrationApp $app, string $nip, string $status, Request $request): void
    {
        IntegrationAppLog::create([
            'integration_app_id' => $app?->id,
            'nip' => $nip,
            'status' => $status,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
