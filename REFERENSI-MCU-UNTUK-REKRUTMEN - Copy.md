# Referensi Implementasi MCU — buat Project Rekrutmen (Laravel Filament)

Diambil dari implementasi modul Sikartap (Fase 3) di HRIS ini — sistem sejenis rekrutmen tapi tanpa ide inovasi, alurnya: berkas → tes → wawancara → **MCU** → keputusan direktur. Bagian MCU ini yang belum ada di project rekrutmen kamu.

## Konsep Inti

MCU (Medical Check-Up) = pemeriksaan kesehatan calon karyawan sebelum diputuskan diterima. Diisi paralel oleh 4 role klinis berbeda, keputusan final fit/unfit oleh dokter jadi gate, tapi keputusan final status kandidat tetap di tangan HRD (dokter cuma kasih rekomendasi).

## Role & Form (4 buah, independen satu sama lain)

| Role | Form isi apa |
|---|---|
| Perawat | Asesmen awal (tensi, nadi, riwayat kesehatan, dst) |
| Staff Lab | Hasil pemeriksaan laboratorium |
| Staff Radiologi | Hasil rontgen/radiologi (thorax, dst) |
| Dokter | Pemeriksaan akhir + `rekomendasi` fit/unfit — **gate final** |

Tiap role isi form sendiri-sendiri, tidak saling bergantung urutan (paralel), kecuali dokter idealnya isi setelah 3 lainnya (referensi, bukan hard block).

## Struktur Tabel (kolom lengkap, siap dipakai)

Sesuaikan `{prefix}` dengan konvensi project rekrutmen kamu (mis. `recruitment_` atau `candidate_`). Tiap tabel relasi 1:1 ke kandidat (`sikartap_id` ganti jadi `candidate_id` / sesuai nama FK kamu, unique constraint biar beneran 1:1).

### 1. Kolom tambahan di tabel kandidat utama (bukan tabel baru)

```php
$table->enum('status_mcu', ['pending', 'fit', 'unfit'])->default('pending');
$table->text('catatan_mcu')->nullable(); // catatan keputusan final HRD, boleh diisi dari rekomendasi dokter
```

Ini yang jadi gate lanjut ke tahap berikutnya (keputusan akhir/offering), diisi manual oleh HRD lewat action "Putuskan Hasil MCU" — BUKAN auto dari `rekomendasi` dokter.

### 2. `{prefix}_mcu_nurse_assessments` (perawat)

```php
$table->ulid('id')->primary();
$table->foreignUlid('candidate_id')->unique()->constrained()->cascadeOnDelete();

$table->decimal('tinggi_badan', 5, 1);
$table->decimal('berat_badan', 5, 1);
$table->string('tekanan_darah');
$table->unsignedSmallInteger('nadi');
$table->decimal('suhu', 4, 1);
$table->unsignedSmallInteger('pernafasan');

$table->text('keluhan_kesehatan')->nullable();

// Riwayat Penyakit Keluarga (rpk)
$table->boolean('rpk_hipertensi')->default(false);
$table->boolean('rpk_stroke')->default(false);
$table->boolean('rpk_jantung')->default(false);
$table->boolean('rpk_diabetes')->default(false);
$table->string('rpk_lainnya')->nullable();

$table->text('riwayat_penyakit_dahulu')->nullable();
$table->text('riwayat_operasi')->nullable();

$table->boolean('alergi_makanan')->default(false);
$table->boolean('alergi_obat')->default(false);
$table->boolean('alergi_debu')->default(false);
$table->string('alergi_lainnya')->nullable();

$table->boolean('kebiasaan_olahraga')->default(false);
$table->boolean('kebiasaan_merokok')->default(false);
$table->boolean('kebiasaan_alkohol')->default(false);
$table->boolean('kebiasaan_obat')->default(false);
$table->string('kebiasaan_lainnya')->nullable();

$table->string('hasil_ekg')->nullable();
$table->text('keterangan_ekg')->nullable();
$table->string('file_ekg')->nullable(); // path upload, disk public + nama random (jangan predictable, lihat § Red Flag)
$table->text('catatan')->nullable();

$table->timestamps();
```

### 3. `{prefix}_mcu_lab_results` (lab)

```php
$table->ulid('id')->primary();
$table->foreignUlid('candidate_id')->unique()->constrained()->cascadeOnDelete();

foreach ([
    'hemoglobin', 'leukosit', 'eritrosit', 'trombosit', 'hematokrit',
    'basofil', 'eosinofil', 'stab', 'segmen', 'limfosit', 'monosit',
    'mcv', 'mch', 'mchc', 'gds', 'gda',
] as $field) {
    $table->string($field)->nullable(); // hasil lab ditulis string ("dalam batas normal" dsb), bukan angka murni
}

$table->string('hbsag')->nullable();
$table->string('hiv')->nullable();
$table->text('kesimpulan')->nullable();
$table->string('file_path')->nullable(); // upload hasil scan lab

$table->timestamps();
```

### 4. `{prefix}_mcu_radiology_results` (radiologi)

```php
$table->ulid('id')->primary();
$table->foreignUlid('candidate_id')->unique()->constrained()->cascadeOnDelete();

$table->string('jenis_pemeriksaan')->nullable();
$table->string('foto_thorax')->nullable(); // "Normal"/"Abnormal" — field terpisah dari file upload-nya
$table->string('jantung')->nullable();
$table->string('paru_kanan')->nullable();
$table->string('paru_kiri')->nullable();
$table->string('diafragma')->nullable();
$table->string('tulang')->nullable();
$table->text('kesimpulan')->nullable();
$table->string('file_path')->nullable(); // upload foto rontgen

$table->timestamps();
```

### 5. `{prefix}_mcu_doctor_examinations` (dokter — gate final)

```php
$table->ulid('id')->primary();
$table->foreignUlid('candidate_id')->unique()->constrained()->cascadeOnDelete();

foreach ([
    'kulit', 'rambut', 'visus_mata', 'buta_warna', 'lapang_pandang',
    'hidung', 'telinga', 'tonsil', 'faring', 'gigi',
    'kelenjar_gondok',
    'suara_pernafasan', 'ronchi', 'wheezing', 'lainnya_paru', 'jantung',
    'hati', 'limpa', 'epigastrium', 'ginjal', 'lainnya_abdomen',
    'ekstremitas', 'neurologi',
] as $field) {
    $table->string($field)->nullable(); // tiap field pemeriksaan fisik per-organ, isi bebas ("normal", "ada kelainan", dst)
}

$table->text('diagnosa')->nullable();
$table->text('rekomendasi')->nullable(); // teks bebas/saran dokter — BUKAN enum fit/unfit, itu ada di kolom status_mcu kandidat (§1)
$table->text('catatan')->nullable();

$table->timestamps();
```

**Kenapa `rekomendasi` dokter text bebas, bukan enum fit/unfit langsung?** Karena keputusan final `status_mcu` sengaja dipisah — dokter kasih rekomendasi/saran tertulis, HRD yang klik keputusan eksplisit fit/unfit di kolom §1. Kalau digabung (dokter langsung set status final), hilang jejak "siapa yang beneran mutusin" dan gak ada ruang HRD override kalau ada pertimbangan lain di luar medis murni.

## Gate & Alur

1. MCU cuma bisa diisi kalau kandidat sudah lulus tahap sebelumnya (mis. `status_wawancara = disarankan/lulus`) — cek di `mount()` tiap halaman form, jangan cuma andalkan permission.
2. 4 form independen, isi kapan saja setelah gate #1 lolos — **tidak perlu tabel jadwal MCU terpisah** kecuali project kamu memang butuh booking slot eksplisit. Di Sikartap sengaja tidak dibuat tabel jadwal, staff klinis isi kapan saja (lihat ⚠️ simplifikasi di `knowledge/markdown/analisis-sikartap-migrasi.md`).
3. `rekomendasi` dokter (fit/unfit) BUKAN penentu otomatis `status_mcu` — HRD yang submit keputusan final `status_mcu` secara eksplisit (gate: pemeriksaan dokter harus sudah ada dulu). Ini niru pola legacy yang benar: dokter kasih saran, keputusan tetap manusia HRD yang klik.

## Halaman/UX yang Perlu Dibuat

- 4 halaman form per role (portal internal staff klinis, gate permission per role — jangan 1 permission umum, biar staff lab gak bisa isi radiologi).
- 1 halaman "Antrian MCU" — gabungan pengganti 4 dashboard staff terpisah, filter kandidat yang eligible MCU tapi belum lengkap, tombol per form cuma muncul sesuai permission user yang login.
- Action "Putuskan MCU" (fit/unfit) di halaman detail kandidat sisi HRD, gated: pemeriksaan dokter sudah diisi.

## Red Flag yang Wajib Dihindari (dari sistem lama yang dianalisis)

1. Jangan hardcode role/permission per form — pakai permission granular (`CanInput...McuPerawat`, dst), jangan 1 flag umum.
2. Jangan biarkan MCU bisa diisi sebelum tahap sebelumnya lulus — cek gate eksplisit, bukan cuma sembunyikan tombol di UI.
3. Jangan otomatis set status kandidat "diterima" begitu MCU fit — tetap butuh keputusan manusia di tahap berikutnya.
4. IDOR — kalau ada upload file hasil (foto rontgen dst), cek kepemilikan/relasi ke kandidat yang benar, bukan cuma cek login.

## Test yang Disarankan

Per role: submit form berhasil, blocked kalau gate tahap sebelumnya belum lolos, blocked kalau bukan role yang tepat. Plus 1 test: keputusan MCU HRD blocked sebelum pemeriksaan dokter ada.

---
Referensi detail implementasi lengkap (kode nyata, HRIS ini): `knowledge/markdown/analisis-sikartap-migrasi.md` § F, `knowledge/markdown/rencana-implementasi-sikartap.md` § Fase 3.
