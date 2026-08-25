# Analisis Sarpras — Persiapan Migrasi ke HRIS

Sumber: `another-app/sarpras/sarpras-php-v2/sarpras` (PHP 8 murni, tanpa framework, PDO). Sistem Permohonan Sarana & Prasarana RSU Syifa Medika — alur pengajuan perbaikan/pengadaan barang oleh unit pelayanan, ditelaah Kabid Yanmed, diputuskan per-item oleh Direktur.

Artifact visual (ERD, diagram alur, diagram status): dibuat 2026-08-25, lihat riwayat percakapan sesi ini — tidak disalin ulang di sini, dokumen ini fokus pada temuan tertulis + arah migrasi.

## A. Kualitas & Arsitektur

Beda jauh dari legacy lain yang sudah dianalisis (Cuti, Sikartap) — ini proyek **baru, ditulis rapi, sadar keamanan**:

- Prepared statement di semua query, password `password_hash()` (bcrypt), CSRF token di tiap form penulis data.
- Hak akses dicek ulang di server pada tiap aksi (`proses.php`, `cetak.php`, `file.php`) — bukan cuma sembunyikan tombol; README eksplisit bilang "permintaan HTTP yang dibuat manual pun tetap ditolak".
- Upload file divalidasi dari **isi berkas** (`finfo`), bukan ekstensi nama — skrip `.php` menyamar `.jpg` tetap ditolak. Disimpan dengan nama acak.
- Tidak ada data produksi nyata — 14 akun awal semuanya dummy/contoh (`dr. Siti Nurhaliza, MARS`, dll), password sama semua (`admin123`). Jadi **tidak ada beban migrasi data lama** seperti kasus Cuti (`sisa_cuti`) atau Sikartap (dump usang).

## B. Role & Model Data

4 role flat: `admin`, `unit`, `kabid`, `direktur` (kolom `enum` di `users.role`, bukan tabel role terpisah). 11 unit pelayanan medis (Ruang 2A/3A/4A/2B/3B/4B, IGD, IRJA/Dialisis/Rehab, ICU/SU/NICU, IBS, RCP) + Kabid Pelayanan Medis + Direktur + Administrator.

6 tabel: `users`, `permohonan` (kepala dokumen), `permohonan_item` (baris item — inti fitur persetujuan sebagian), `lampiran`, `ttd` (tanda tangan barcode), `riwayat` (audit trail append-only). Semua tabel anak `ON DELETE CASCADE` ke `permohonan`.

## C. Alur & Aturan Bisnis Kunci

Alur: **Unit ajukan → Kabid teruskan → Direktur putuskan per item → cetak dokumen → unit tutup pengerjaan.**

7 status kepala dokumen (`diajukan`, `menunggu_direktur`, `diputuskan_sebagian`, `disetujui`, `disetujui_sebagian`, `ditolak`, `selesai`), **dihitung ulang otomatis** dari status seluruh item lewat `segarkan_status()` (`includes/functions.php`) — bukan ditulis manual di tiap aksi. Selama ada item `menunggu`, dokumen tetap nongkrong di antrean Direktur.

Aturan yang tidak kelihatan dari nama kolom:

1. `jumlah_disetujui` dipangkas server ke maksimum `jumlah` diminta — Direktur cuma bisa kasih lebih sedikit, tidak pernah lebih banyak.
2. Item yang statusnya sudah berubah dari `menunggu` **terkunci**, termasuk lewat request HTTP yang dimanipulasi.
3. Tombol cetak (`boleh_cetak()`) baru aktif setelah **seluruh** item diputuskan **dan** minimal 1 disetujui — dokumen kosong tidak bisa dicetak.
4. Tanda tangan unik per `(permohonan_id, peran)` — 1 kali per peran per dokumen, dibubuhkan Unit saat ajukan, Kabid saat meneruskan, Direktur pada keputusan pertama.
5. Barcode Code 128 dibangkitkan sendiri di PHP (`includes/barcode.php`, tanpa pustaka luar) — kode tanda tangan sengaja pendek (14 karakter, mis. `260001D-7EAA17`) supaya lebar modul barcode tetap di atas ambang baca pemindai pada kolom TTD kertas A4 (~59mm). README mendokumentasikan hasil uji: lebar modul di bawah 1,5 gagal dipindai.
6. Halaman `verifikasi.php` sengaja bisa diakses **tanpa login** — siapa pun bisa memeriksa keaslian dokumen cetak dari kode barcode-nya.

## D. Gap Terhadap Approval Engine HRIS

Approval Engine HRIS (`ApprovalModuleEnum`, `MasterApprovalFlows`/`MasterApprovalFlowSteps`, snapshot per pengajuan) itu **per-step whole-request** — 1 approver putuskan approve/reject untuk 1 pengajuan utuh. Sarpras butuh granularitas **per-item di dalam 1 pengajuan** (Direktur bisa setuju sebagian item, tolak sebagian, pangkas jumlah) — ini **tidak ada preseden di modul HRIS mana pun** (Attendance, Manpower, Coaching, dst semua whole-request).

Konsekuensi buat desain migrasi: enum modul baru + flow config Unit→Kabid→Direktur bisa langsung reuse Approval Engine apa adanya (persis pola 11 modul lain). Tapi keputusan per-item **harus** hidup di tabel domain sendiri (analog `permohonan_item`) di luar tabel approval engine — step "Direktur" di engine cukup resolve *approved* begitu semua item selesai diputuskan (apa pun campurannya), detail per-item disimpan & ditampilkan terpisah. Pola ini sama seperti ManpowerRequests: approval engine buat alur/notifikasi, tabel domain sendiri buat detail bisnisnya.

Dua kapabilitas lain yang juga tidak ada preseden di HRIS sama sekali: **tanda tangan elektronik berbarcode** (gak ada modul approval mana pun di HRIS yang punya ini) dan **dokumen cetak resmi bergerbang status + halaman verifikasi publik tanpa login**.

## E. Keputusan yang Sudah Diambil (2026-08-25)

- Scope migrasi: **full parity** — bukan cuma alur approval, tapi termasuk barcode ttd, dokumen cetak, dan halaman verifikasi publik (dikonfirmasi user, lihat pertanyaan scope di sesi ini).
- Pendekatan approval: enum baru di `ApprovalModuleEnum` (`SARPRAS_REQUEST`) + reuse Approval Engine buat routing Unit→Kabid→Direktur, **plus** tabel domain terpisah buat item & keputusan per-item (lihat §D).
- Rencana implementasi detail: [`planning/2026-08-25-migrasi-sarpras-ke-hris.md`](../../planning/2026-08-25-migrasi-sarpras-ke-hris.md).
