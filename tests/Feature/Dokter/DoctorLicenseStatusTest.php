<?php

namespace Tests\Feature\Dokter;

use App\Models\Doctor\DoctorLicense;
use Tests\TestCase;

/** Status lisensi dihitung dari tanggal_berakhir: aktif / akan expired (<= 90 hari) / expired; nonaktif tetap nonaktif. */
class DoctorLicenseStatusTest extends TestCase
{
    private function effective(?string $berakhir, string $status = 'aktif'): string
    {
        return (new DoctorLicense(['tanggal_berakhir' => $berakhir, 'status' => $status]))->status_efektif;
    }

    public function test_effective_status_follows_expiry_date(): void
    {
        $this->assertSame('aktif', $this->effective(null));
        $this->assertSame('aktif', $this->effective(now()->addDays(91)->toDateString()));
        $this->assertSame('akan_expired', $this->effective(now()->addDays(90)->toDateString()));
        $this->assertSame('akan_expired', $this->effective(now()->toDateString())); // berakhir hari ini masih berlaku
        $this->assertSame('expired', $this->effective(now()->subDay()->toDateString()));
        $this->assertSame('nonaktif', $this->effective(now()->subYear()->toDateString(), 'nonaktif'));
    }
}
