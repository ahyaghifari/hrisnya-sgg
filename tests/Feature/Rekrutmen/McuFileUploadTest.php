<?php

namespace Tests\Feature\Rekrutmen;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Rekrutmen\Mcu\Concerns\HandlesMcuFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MCU rekrutmen dipisah dari MCU Sikartap (keputusan user 2026-09-29): semua stasiun cukup unggah
 * berkas hasilnya. Yang diuji di sini penanganan berkasnya - itu bagian yang paling gampang salah,
 * dan berkasnya data kesehatan jadi tidak boleh nyasar ke disk publik.
 *
 * ponytail: tanpa database. McuInvitee & kawan-kawannya ada di koneksi `recruitment` (MySQL)
 * sementara suite ini sqlite in-memory, jadi alur simpan per halaman tidak bisa diuji di sini.
 */
class McuFileUploadTest extends TestCase
{
    private function subject(?string $existing = null): object
    {
        $obj = new class
        {
            use HandlesMcuFileUpload;

            protected function stationPermission(): string
            {
                return CustomPermissionEnum::INPUT_MCU_LABORATORIUM->value;
            }

            public function store(string $inviteeId): ?string
            {
                return $this->resolveFilePath($inviteeId);
            }
        };

        $obj->existingFilePath = $existing;

        return $obj;
    }

    public function test_an_uploaded_file_lands_on_the_private_disk_under_the_invitee_folder(): void
    {
        Storage::fake('local');

        $subject = $this->subject();
        $subject->file = UploadedFile::fake()->create('hasil-lab.pdf', 120, 'application/pdf');

        $path = $subject->store('INV123');

        $this->assertStringStartsWith('mcu-rekrutmen/INV123/', $path);
        Storage::disk('local')->assertExists($path);
    }

    /** Tanpa unggahan baru, berkas yang sudah ada dipertahankan - bukan dihapus jadi kosong. */
    public function test_saving_without_a_new_file_keeps_the_existing_one(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('mcu-rekrutmen/INV123/lama.pdf', 'isi');

        $path = $this->subject('mcu-rekrutmen/INV123/lama.pdf')->store('INV123');

        $this->assertSame('mcu-rekrutmen/INV123/lama.pdf', $path);
        Storage::disk('local')->assertExists('mcu-rekrutmen/INV123/lama.pdf');
    }

    /** Ganti berkas: yang baru tersimpan, yang lama dibuang supaya tidak menumpuk. */
    public function test_replacing_a_file_deletes_the_previous_one(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('mcu-rekrutmen/INV123/lama.pdf', 'isi');

        $subject = $this->subject('mcu-rekrutmen/INV123/lama.pdf');
        $subject->file = UploadedFile::fake()->create('baru.pdf', 80, 'application/pdf');

        $path = $subject->store('INV123');

        $this->assertNotSame('mcu-rekrutmen/INV123/lama.pdf', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertMissing('mcu-rekrutmen/INV123/lama.pdf');
    }

    /** Berkasnya data kesehatan - tidak boleh ikut nongkrong di disk publik. */
    public function test_the_file_never_touches_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $subject = $this->subject();
        $subject->file = UploadedFile::fake()->create('hasil.pdf', 50, 'application/pdf');

        $path = $subject->store('INV123');

        Storage::disk('public')->assertMissing($path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
