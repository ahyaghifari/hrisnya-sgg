# TTE + QR Verification — Fase 1 (internal, TANPA sertifikasi PSrE)

> **Direvisi 2026-09-29**: keputusan "1 dokumen = 1 kode verifikasi tetap" di §0/§100 di bawah
> SUDAH TIDAK BERLAKU — diganti "1 TTD = 1 kode verifikasi PERSONAL" (instruksi user: tanda
> tangan itu unik & menyangkut langsung ke personal, jangan digabung 1 QR buat semua penanda
> tangan). `document_verifications` sekarang 1:1 ke `document_signatures` (bukan ke `documents`
> lagi) — lihat migration `2026_10_06_090500_move_document_verification_code_to_signature.php`,
> `App\Models\Concerns\HasDocumentSignatures::sign()`, dan
> `App\Http\Controllers\Public\VerificationController`. Sisa dokumen di bawah ini (skema §1,
> alasan §0) dibiarkan sebagai jejak keputusan AWAL, bukan diperbarui ulang kalimat per kalimat.
>
> Dibuat 2026-09-28, direvisi 2026-09-28 (balik pakai tabel registry `documents` generik —
> lihat §0 baris "Tabel `documents`"). Turunan dari `docs/HRIS_TTE_QR_Verification_Architecture.md`
> (baseline arsitektur umum, masih berlaku sebagai referensi konsep) — dokumen ini mempersempit
> jadi **rencana eksekusi Fase 1** berdasarkan keputusan yang sudah diambil di sesi diskusi
> 2026-09-27/28:
>
> - **Internal-only.** Tidak ada TTE tersertifikasi (PSrE) sama sekali di fase ini.
> - **Signing model: hash + kode verifikasi acak**, BUKAN cryptographic signature (RSA/ECDSA).
>   Alasan: status hukum internal sama saja (tetap "tidak tersertifikasi") baik pakai key pair
>   atau tidak — RSA cuma nambah beban (key generation, secure storage, rotation) tanpa nilai
>   tambah nyata selama ancamannya bukan "DB dibobol lalu diubah langsung". Upgrade ke crypto
>   penuh gampang dilakukan belakangan (tinggal tambah `signature_type=certified` + provider baru)
>   kalau nanti kebutuhannya berubah.
> - **Tabel `documents` sebagai REGISTRY TIPIS** — 1 baris per dokumen terbit (nomor, judul,
>   jenis, tanggal terbit), nempel ke tabel sumber asli (`employee_sps`, dst) via polymorphic.
>   Alasannya: butuh 1 halaman "Semua Dokumen Terbit" lintas jenis (SP, Surat Tugas, Berita
>   Acara, dst) tanpa `UNION` manual banyak tabel. BEDA dari pola `documents` di baseline
>   arsitektur (yang nyimpen `file_path`/`file_hash`/`status` sendiri) dan BEDA dari
>   `user_documents` lama (arsip surat manual, dihapus 2026-10-05, 0 baris production, isinya
>   DUPLIKAT konten) — `documents` di sini TIDAK menyimpan isi surat sama sekali, isi surat
>   (poin pelanggaran SP, klarifikasi Berita Acara, dst) tetap 100% di tabel sumber masing-masing.
>   `document_types` TIDAK dibuat sebagai tabel DB terpisah — cukup PHP enum (`App\Enum\DocumentType`)
>   untuk Fase 1, karena belum ada kebutuhan admin mengatur jenis dokumen sendiri lewat UI (YAGNI).
> - **QR, bukan barcode Code 128** (beda dari `sarpras/` — project terpisah, bukan bagian HRIS).
>   QR bisa discan kamera HP langsung, tidak butuh scanner fisik.
>
> Menyasar dokumen yang **SUDAH bisa digenerate** sekarang: Surat Peringatan (`EmployeeSp` +
> `SpPdfService`) dan Berita Acara (`SpReport`, PDF sama). Surat Tugas belum ada modulnya —
> disiapkan pola yang sama, dieksekusi belakangan begitu modulnya dibuat.

---

## 0. Keputusan

| Topik | Keputusan | Alasan |
|---|---|---|
| Cryptographic signature (RSA/key pair) | **TIDAK di Fase 1.** | Lihat blockquote di atas — tidak nambah kekuatan hukum, nambah kompleksitas. |
| Tabel `documents` | **DIBUAT, tapi TIPIS** (nomor, judul, jenis, tanggal terbit + polymorphic ke sumber) — bukan penyimpan isi surat. | Butuh listing lintas jenis (§1). Isi surat tetap di tabel sumber, jadi tidak mengulang duplikasi konten ala `user_documents` lama. |
| Tabel `document_types` | **TIDAK dibuat sebagai tabel DB** — cukup PHP enum. | Belum ada kebutuhan admin atur jenis dokumen sendiri lewat UI. Tambah tabel kalau kebutuhan itu muncul (YAGNI). |
| Cara nempel ke dokumen sumber | `documents` polymorphic (`documentable_type`/`documentable_id`) ke model ASLI (`EmployeeSp`, dst) — SATU KALI hop polymorphic. `document_signatures`/`document_verifications` lalu FK biasa ke `documents.id` (bukan polymorphic lagi), lebih sederhana dari double-polymorphic. | Nol perubahan skema ke tabel sumber yang sudah stabil; gampang tambah jenis dokumen baru (Surat Tugas) tanpa migrasi ke tabel lama; query TTD/verifikasi tinggal join biasa lewat `document_id`. |
| Kapan "ditandatangani" | **Otomatis saat dokumen terbit** (SP: saat `CreateSpPage` submit) — bukan langkah "Tanda Tangani" terpisah. | SP tidak punya alur approval berjenjang sebelum terbit (beda dari baseline arsitektur umum yang asumsi ada Approval Workflow dulu) — `created_by`/`knows_by` sudah final begitu baris `employee_sps` dibuat. Kalau nanti Surat Tugas punya approval berjenjang, sign dipicu setelah approval selesai, bukan saat draft dibuat — prinsip "sign setelah final" tetap dipegang, cuma titik pemicunya beda per jenis dokumen. |
| Hash dihitung dari apa | **Snapshot JSON kanonis** (field-field yang relevan: item SP, keputusan, nama/jabatan penanda tangan, tanggal), BUKAN dari file PDF mentah. | PDF di-generate ON-DEMAND dari data live (`SpPdfService`), bukan file statis tersimpan — hash PDF binary tidak stabil (font embedding, timestamp render bisa beda). Hash atas data terstruktur jauh lebih stabil & gampang diverifikasi ulang. |
| Deteksi dokumen berubah setelah sign | **Bandingkan hash tersimpan vs hash dihitung ulang saat verifikasi dibuka.** | `EmployeeSp` saat ini TIDAK PUNYA jalur edit sama sekali setelah dibuat (`SuratPeringatanDetailPage` read-only) — jadi secara praktik hash tidak akan pernah mismatch untuk SP hari ini. Tetap dipasang sebagai jaring pengaman kalau nanti ada fitur edit/perubahan manual DB. |
| Siapa "penanda tangan" | Bisa lebih dari 1 per dokumen, dibedakan `signer_role` (string bebas per jenis dokumen, mis. `created_by`, `knows_by` untuk SP). | Beda dari sarpras yang perannya fix (unit/kabid/direktur) — jenis dokumen HRIS bisa beda-beda struktur penanda tangannya. |
| Library QR | **`simplesoftwareio/simple-qrcode`** — SUDAH ada di `composer.json`, tidak perlu tambah dependency baru. | Ladder rung 5 (dependency yang sudah terpasang). |
| Isi QR | **URL verifikasi publik** (`/verify/{code}`), BUKAN data mentah dokumen. | Konsisten baseline arsitektur §16 — QR cuma entry point, bukan pembawa data. |
| Halaman verifikasi | **Publik, tanpa login** (pola sarpras `verifikasi.php`) — tampilkan info minimal (jenis dokumen, nomor, penanda tangan, tanggal, status valid/dicabut), BUKAN isi lengkap dokumen. | Biar bisa diperiksa pihak luar tanpa akses HRIS, tanpa bocorin data HR sensitif. |
| Status "dicabut" | **Dihitung LIVE dari model asli** (`EmployeeSp::isCancelled()`), bukan disimpan ulang di tabel verifikasi. | `cancelled_at` sudah ada di `employee_sps` — jangan duplikasi state yang bisa mencong dari sumber asli. |
| Kode verifikasi | Random, cryptographically secure (`Str::random()` via `random_bytes`), BUKAN incremental ID. Panjang bebas (tidak terikat batas cetak barcode kayak sarpras — QR toleran karakter lebih banyak). | Baseline arsitektur §27 — jangan pakai ID DB sebagai kode publik. |

---

## 1. Skema DB — 3 tabel baru, TANPA ubah tabel dokumen yang sudah ada

### `documents` — registry tipis, satu-satunya yang polymorphic ke sumber

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `documentable_type` | string | morph type (`App\Models\Sp\EmployeeSp`, dst) |
| `documentable_id` | ulid | morph id |
| `document_type` | string | value dari `App\Enum\DocumentType` (`sp`, `berita_acara`, `surat_tugas`, dst) — dipakai buat filter listing & label ikon per jenis |
| `document_number` | string | snapshot `documentNumber()` sumbernya, biar listing bisa `ORDER BY`/cari tanpa join |
| `title` | string | label ringkas listing, mis. "SP II — Budi Santoso" |
| `issued_at` | timestamp | kapan dokumen ini terbit (bukan `created_at` baris registry, meski biasanya sama) |
| `created_by` | foreignUlid → `users`, nullable | `nullOnDelete` |
| `timestamps` | | |

Index: unique `(documentable_type, documentable_id)` — 1 dokumen sumber = maksimal 1 baris
registry. Index tambahan `(document_type, issued_at)` buat listing per jenis terurut tanggal.

**Yang SENGAJA TIDAK ada di sini:** isi surat (poin pelanggaran, klarifikasi, dst — tetap di
tabel sumber), `status`/`file_path`/`file_hash` (itu semua ranah `document_signatures`/
`document_verifications` di bawah, atau dihitung live dari sumber via kontrak `Signable`).

### `document_signatures`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `document_id` | foreignUlid → `documents`, `cascadeOnDelete` | FK biasa, BUKAN polymorphic lagi (polymorphic-nya cukup sekali di `documents`) |
| `signer_role` | string | bebas per jenis dokumen — SP: `created_by` \| `knows_by` |
| `signer_id` | foreignUlid → `users`, nullable | `nullOnDelete` — akun bisa dihapus, riwayat TTD tetap ada |
| `signer_name` | string | snapshot nama saat sign, jangan bergantung ke `users.name` yang bisa berubah |
| `signer_position` | string, nullable | snapshot jabatan saat sign |
| `document_hash` | string (64, sha256 hex) | hash snapshot kanonis, lihat §3 |
| `signed_at` | timestamp | |
| `timestamps` | | |

Index: `(document_id)`.

### `document_verifications`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `document_id` | foreignUlid → `documents`, unique, `cascadeOnDelete` | 1:1 — 1 dokumen = 1 kode verifikasi tetap |
| `verification_code` | string, unique | random, dibangkitkan sekali per dokumen (bukan per signature — 1 dokumen bisa punya beberapa TTD tapi 1 kode verifikasi) |
| `timestamps` | | |

Index: unique `(document_id)`, unique `(verification_code)`.

Tidak ada kolom `status`/`expires_at` di tabel ini — status dicabut/berlaku dihitung live dari
model dokumen aslinya (lihat kontrak `Signable` di §2, via `documents.documentable`). Kalau
nanti ada jenis dokumen yang butuh masa berlaku (`expires_at`), tambah sebagai kolom saat
dibutuhkan, jangan diprediksi dari sekarang (YAGNI).

---

## 2. Kontrak `Signable` — 1 interface kecil, tiap model dokumen implementasi sendiri

```php
namespace App\Contracts;

interface Signable
{
    /** Data yang di-hash - field yang relevan buat isi dokumen final, BUKAN kolom teknis
     * (id, timestamps, dsb). Urutan key harus stabil (ksort atau array asosiatif eksplisit)
     * supaya json_encode konsisten tiap dipanggil. */
    public function signablePayload(): array;

    public function documentNumber(): string;
    public function documentTypeLabel(): string;
    public function isRevoked(): bool;
    public function revokedReason(): ?string;
}
```

`App\Models\Sp\EmployeeSp implements Signable`:
- `signablePayload()`: `nomor`, `user_id`, `sp_level`, tiap `items` (poin pelanggaran, urut
  `sort_order`), `report` (klarifikasi, kesimpulan — kalau ada), `start_date`/`end_date`,
  `created_by`/`knows_by`.
- `documentNumber()`: `nomor`.
- `documentTypeLabel()`: `"Surat Peringatan {$sp_level}"`.
- `isRevoked()`: `$this->isCancelled()` (sudah ada).
- `revokedReason()`: `$this->cancelled_reason`.

Hash = `hash('sha256', json_encode($model->signablePayload()))`, dihitung sekali saat sign
(§4) dan disimpan di `document_signatures.document_hash` per baris tanda tangan. Halaman
verifikasi (§5) hitung ulang lalu bandingkan — beda berarti data berubah setelah ditandatangani.

---

## 3. Trait pembantu — `HasDocumentSignatures`

Supaya tiap model `Signable` tidak nulis ulang query yang sama. Beda dari draf sebelumnya:
sekarang ngelewatin `Document` (registry) dulu, `signatures`/`verification` gantung di situ,
bukan langsung polymorphic ke model sumber.

```php
trait HasDocumentSignatures
{
    public function document(): MorphOne // -> documents (documentable_type/id)

    /** Bikin baris registry `documents` kalau belum ada - dipanggil sekali di titik pemicu (§4)
     * sebelum sign() pertama kali. Idempotent - dokumen yang sudah punya registry tidak dobel. */
    public function issueDocument(\App\Enum\DocumentType $type, string $title, ?User $issuer = null): Document
    {
        return $this->document ?? $this->document()->create([
            'document_type'   => $type->value,
            'document_number' => $this->documentNumber(),
            'title'           => $title,
            'issued_at'       => now(),
            'created_by'      => $issuer?->id,
        ]);
    }

    public function sign(User $signer, string $role): DocumentSignature
    {
        return $this->document->signatures()->create([
            'signer_role'     => $role,
            'signer_id'       => $signer->id,
            'signer_name'     => $signer->name,
            'signer_position' => $signer->activeJobPosition?->jobPosition?->name,
            'document_hash'   => hash('sha256', json_encode($this->signablePayload())),
            'signed_at'       => now(),
        ]);
    }

    public function ensureVerificationCode(): DocumentVerification
    {
        return $this->document->verification ?? $this->document->verification()->create([
            'verification_code' => strtoupper(Str::random(10)), // lihat §0 soal panjang/charset
        ]);
    }
}
```

`Document` model: `documentable(): MorphTo`, `signatures(): HasMany`, `verification(): HasOne`.

`EmployeeSp use HasDocumentSignatures;` di samping `implements Signable`.

---

## 4. Titik pemicu sign — hook ke alur yang SUDAH ADA, bukan tombol baru

SP tidak punya langkah approval terpisah sebelum terbit — jadi sign terjadi otomatis begitu
`CreateSpPage` selesai simpan (`EmployeeSp` + `SpItem[]` + `SpReport?`):

```php
// di akhir CreateSpPage::save() / SpService setelah $sp, $items, $report tersimpan
$sp->issueDocument(\App\Enum\DocumentType::SP, "SP {$sp->sp_level->value} — {$sp->user->name}", $sp->createdBy);
$sp->sign($sp->createdBy, 'created_by');
if ($sp->knows_by) {
    $sp->sign($sp->knowsBy, 'knows_by');
}
$sp->ensureVerificationCode();
```

Kalau `knows_by` diisi belakangan (approval offline/verbal, field nullable — lihat komentar di
`EmployeeSp::knowsBy()`), TTD `knows_by` menyusul saat field itu diisi. **Belum diputuskan** —
lihat §8.

Berita Acara (`SpReport`) 1:1 dengan `EmployeeSp` — opsi paling sederhana: anggap Berita Acara
ikut ditandatangani bareng SP induknya (1 set tanda tangan buat 2 dokumen terkait), BUKAN
`Signable` terpisah. Lebih murah daripada bikin 2x lipat baris signature/verifikasi untuk
sepasang dokumen yang selalu terbit bareng. **Perlu dikonfirmasi user** (§8).

---

## 5. Verifikasi publik

### Route + Controller

```
GET /verify/{code}  ->  VerificationController::show
```

Controller: cari `document_verifications` by `verification_code` → `documents` (via `document_id`)
→ `documentable` (morph ke model asli, buat `isRevoked()`/`signablePayload()`) + `signatures` →
render halaman publik (tanpa login, pola `sarpras/verifikasi.php`).

Data yang TAMPIL (aman buat publik):
- Jenis dokumen (`documentTypeLabel()`), nomor (`documentNumber()`)
- Tiap penanda tangan: nama, jabatan (snapshot), peran, waktu tanda tangan
- Status: **SAH** (hash cocok) / **⚠ DATA BERUBAH SETELAH DITANDATANGANI** (hash beda) /
  **DICABUT** (`isRevoked()` true, tampilkan `revokedReason()`)

Data yang **TIDAK** tampil: isi poin pelanggaran SP, klarifikasi Berita Acara, data personal
lain di luar yang disebut di atas — sama prinsipnya dengan baseline arsitektur §17 & sarpras
(`verifikasi.php` cuma nunjukin keabsahan, bukan isi permohonan).

### View

Halaman sederhana, 1 kolom, kerangka minim (boleh reuse layout guest kalau ada, atau berdiri
sendiri kayak `login.blade.php`) — TIDAK perlu dashboard/nav penuh karena diakses orang luar
yang belum tentu punya akun HRIS.

---

## 6. Halaman "Semua Dokumen Terbit" — alasan utama `documents` dibuat

Resource/Livewire page admin, 1 tabel, sumbernya query polos ke `documents` — TIDAK perlu
`UNION`/join manual ke tiap tabel sumber untuk sekadar menampilkan listing:

```php
Document::query()
    ->when($filterType, fn ($q) => $q->where('document_type', $filterType))
    ->latest('issued_at')
    ->paginate();
```

Kolom tabel: Jenis (badge per `document_type`, ikon beda per jenis), Nomor, Judul, Terbit oleh,
Tanggal terbit, jumlah TTD (`signatures_count`), status (SAH/DICABUT — dihitung dari
`documentable->isRevoked()`, N+1 kalau tidak eager-load, lihat catatan performa di bawah), link
"Lihat" yang mengarah ke halaman detail ASLI-nya (`SuratPeringatanDetailPage`, dst — resolve
route per `document_type`, bukan halaman detail generik baru).

**Catatan performa:** `documentable` beda tipe per baris (`EmployeeSp` hari ini, `SuratTugas`
nanti) — `Document::with('documentable')` tetap jalan (Eloquent morphTo eager load per tipe
otomatis dikelompokkan), tapi kalau daftar sudah ratusan baris & butuh cepat, pertimbangkan
cache `is_revoked`/`revoked_reason` ke `documents` sendiri lewat event listener saat sumber
di-cancel (`EmployeeSp::cancelled_at` berubah) — TAPI ini nambah state yang bisa mencong dari
sumber. **Jangan lakukan di awal** — baru optimasi kalau listing beneran lambat, bukan
diprediksi dari sekarang (YAGNI, sama prinsipnya dengan alasan `status` tidak ada di
`document_verifications`).

---

## 7. Perubahan ke PDF (`SpPdfService` + blade)

- `SpPdfService::loadForPdf()` / `loadForBeritaAcaraPdf()`: tambah eager load `->loadMissing('verification')`.
- `resources/views/pdf/sp/sp.blade.php` & `pdf/sp/berita-acara.blade.php`: tambah blok TTD —
  QR (`QrCode::size(90)->generate($verifyUrl)`, di-embed base64 kayak kop surat yang sudah ada
  di sarpras, pola serupa cocok dipakai di sini juga) + kode verifikasi tercetak di bawahnya +
  nama/jabatan penanda tangan per `signer_role`.
- **QR di-generate SEKALI saat sign** (`ensureVerificationCode()`), bukan tiap kali PDF
  di-render — kode-nya sama terus untuk dokumen yang sama, konsisten dengan prinsip "1 dokumen
  = 1 kode verifikasi tetap" (§0, baseline arsitektur §21: jangan sampai kode berubah-ubah
  yang bikin QR lama di PDF ter-download sebelumnya jadi tidak valid).

---

## 8. Belum diputuskan / perlu konfirmasi user

- **Berita Acara**: ikut tanda tangan SP induknya (1 set), atau `Signable` terpisah dengan
  penanda tangan sendiri (mis. investigator HC-GA, beda dari `created_by`/`knows_by` SP)?
- **`knows_by` diisi belakangan**: TTD-nya menyusul otomatis saat field diisi, atau SP dianggap
  "belum lengkap tanda tangannya" sampai `knows_by` terisi (badge khusus di halaman verifikasi)?
- **Panjang & format `verification_code`**: berapa karakter, huruf besar semua atau campur,
  perlu prefix (mis. tahun+jenis dokumen kayak pola sarpras `260001D-A7F3K9`) atau random polos?
- **Route publik `/verify/{code}`**: perlu rate limiting/throttle supaya tidak dipakai brute-force
  nebak kode (meski panjang & random, tetap praktik baik pasang throttle dasar)?
- **Cakupan Fase 1**: cukup SP + Berita Acara dulu, atau langsung siapkan juga kontrak
  `Signable` buat Surat Tugas sekalipun modul suratnya sendiri belum ada (biar begitu modulnya
  jadi, tinggal `implements Signable` tanpa bongkar skema)?
- **Nasib dokumen yang SUDAH ADA** (SP yang terbit sebelum fitur ini jalan) — dibiarkan tanpa
  TTD/QR (cetak ulang tanpa verifikasi), atau di-backfill (jalankan `sign()`+`ensureVerificationCode()`
  massal lewat command/seeder buat data lama)?
