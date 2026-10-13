# Data Sistem Dokter (Rekrutmen + Panel Dokter)

Daftar data yang disimpan di DB `doctor` (panel `app/Filament/Dokter`). Tanda: **[ADA]** sudah ada di kode, **[USUL]** usulan baru.

## Pembagian Fase

| Fase | Data |
|---|---|
| **Rekrutmen** (saat melamar, sebelum praktik) | §1 identitas, §2 spesialisasi, §5 dokumen lamaran, §7 pendidikan & pelatihan, §8 pengalaman kerja, §9 rekening/NPWP, §10 kontak darurat, §11 MCU, §12 jejak rekrutmen, **STR** (§4) |
| **Pasca-rekrutmen** (setelah diterima & mulai praktik) | §3 penempatan RS, **SIP** (§4), **credentialing** (§6), §13 reminder expiry, §14 audit |

SIP dan credentialing **bukan** data rekrutmen: SIP terbit setelah dokter praktik, credentialing berjalan setelah diterima. Keduanya hanya diisi lewat panel dokter, tidak diminta di form lamaran. Saat melamar cukup STR.

Prinsip: rekrutmen umum (DB `recruitment`) tetap dipakai untuk alur lamaran/wawancara/MCU. Setelah dokter diterima, data induknya pindah ke master `doctors` dan tidak ditimpa ulang. Relasi lintas DB (`hris_user_id`, `rumah_sakit_id`) tanpa FK.

---

## 1. Identitas Dokter — `doctors` [ADA]

| Field | Keterangan |
|---|---|
| nama, gelar_depan, gelar_belakang | Nama disimpan tanpa gelar |
| nip | Nomor induk internal |
| nik | Sensitif, jangan tampil penuh di daftar |
| tempat_lahir, tanggal_lahir, jenis_kelamin, agama | |
| hris_user_id | Akun login HRIS (unik) |
| status | aktif / nonaktif |
| **[USUL]** no_hp, email, alamat_domisili, alamat_ktp, kota, provinsi | Kontak & alamat |
| **[USUL]** status_pernikahan, kewarganegaraan, golongan_darah | |
| **[USUL]** foto | Pas foto profil |
| **[USUL]** jenis_dokter | Umum / Spesialis / Subspesialis / Gigi |
| **[USUL]** status_kepegawaian | Tetap / Kontrak / Mitra (visiting) / Part-time |

## 2. Spesialisasi — `specialties` + `doctor_specialties` [ADA]

- Master: nama, kode, status.
- Pivot: dokter ↔ spesialis, `is_primary` untuk spesialis utama.
- **[USUL]** kolom `subspesialisasi` (teks atau master terpisah) bila dibutuhkan.

## 3. Penempatan di Rumah Sakit — `doctor_hospitals` [ADA] (pasca-rekrutmen)

rumah_sakit_id (`m_companies.id`), tanggal_mulai, tanggal_selesai, status.

- **[USUL]** jabatan (mis. DPJP, Kepala Instalasi), unit/poli, jadwal praktik (hari, jam mulai, jam selesai), jenis ikatan (full/visiting).

## 4. Lisensi: SIP / STR / STR-KT — `doctor_licenses` [ADA]

> STR = fase rekrutmen (wajib). STR-KT = fase rekrutmen, opsional (boleh kosong). SIP = pasca-rekrutmen (terbit setelah praktik).

jenis, nomor, tanggal_terbit, tanggal_berlaku, tanggal_berakhir, doctor_document_id (scan), status (aktif / akan_expired ≤90 hari / expired / nonaktif), notes.

- **[USUL]** untuk SIP: `rumah_sakit_id` (SIP terikat tempat praktik) dan `urutan_sip` (SIP 1/2/3, maks 3 tempat).
- **[USUL]** untuk STR: `kolegium` / organisasi penerbit.

## 5. Dokumen — `doctor_documents` [ADA]

Kolom: document_type, file_path (terenkripsi, disk `local`), file_name, mime_type, file_size, document_number, issued_at, expired_at, status, notes, uploaded_by.

Jenis dokumen yang sudah ada: `LAMARAN`, `CV`, `IJAZAH_DOKTER`, `IJAZAH_SPESIALIS`, `IJAZAH_SUBSPESIALIS`, `TRANSKRIP`, `SERTIFIKAT_KOMPETENSI`, `SERTIFIKAT_PELATIHAN`, `BUKU_REKENING`, `KTP`, `NPWP`, `CREDENTIALING`.

**[USUL]** tambah jenis: `STR`, `SIP`, `SURAT_REKOMENDASI_IDI`, `SURAT_KETERANGAN_SEHAT`, `SKCK`, `BPJS`, `KARTU_KELUARGA`, `PAS_FOTO`, `SERTIFIKAT_ACLS_ATLS_BTCLS`, `SURAT_PERNYATAAN`, `KONTRAK`.

## 6. Credentialing — `credentialing_cases` [ADA] (pasca-rekrutmen)

Satu case = satu proses di satu RS; riwayat tidak ditimpa.

rumah_sakit_id, nomor, jenis (awal / rekredensial), tanggal_pengajuan, status (`draft → pengajuan → verifikasi → kredensial → rekomendasi → sk_spk → selesai / ditolak`), doctor_document_id (PDF final), tanggal_selesai, spk_nomor, spk_terbit, spk_berakhir, notes, created_by.

- **[USUL]** `clinical_privileges` / `kewenangan_klinis`: daftar tindakan yang diizinkan per case (tabel anak: case_id, nama_tindakan, level, disetujui).
- **[USUL]** `credentialing_case_logs`: riwayat perubahan status (status_lama, status_baru, oleh, waktu, catatan) agar ada audit trail.
- **[USUL]** Hasil rapat komite kredensial: tanggal, hasil, catatan.

## 7. Pendidikan & Pelatihan [USUL]

Saat ini hanya ada di data kandidat (`candidate_educations`, `candidate_trainings`). Untuk dokter dibuat versi master:

- `doctor_educations`: jenjang (S1 Kedokteran / Profesi Dokter / Spesialis / Subspesialis), institusi, jurusan/program, tahun_masuk, tahun_lulus, IPK, negara, doctor_document_id (ijazah).
- `doctor_trainings`: nama pelatihan, penyelenggara, tanggal, durasi (SKP/jam), nomor sertifikat, masa berlaku, doctor_document_id. Contoh: ACLS, ATLS, BTCLS.
- **[USUL]** `doctor_cme_points` (SKP IDI): periode, jumlah SKP, bukti.

## 8. Pengalaman Kerja [USUL]

`doctor_work_experiences`: nama_faskes, jabatan, jenis_faskes, tanggal_mulai, tanggal_selesai, alasan_berhenti, is_current, referensi (nama + kontak).

## 9. Keuangan & Pajak [USUL]

Data mengikuti HRIS, tapi dokter tetap perlu menyimpannya.

- `npwp` (nomor), status PTKP.
- Rekening: nama_bank, nomor_rekening, atas_nama (dokumen `BUKU_REKENING` sudah ada).
- **[USUL]** Skema honor: jenis (tetap / fee per tindakan / bagi hasil), nilai/persentase, berlaku_dari. Catatan: tabel `doctor_payroll*` dan `m_doctor_services` lama sudah di-drop, jadi putuskan dulu apakah honor dikelola di sini atau modul lain.

## 10. Keluarga & Kontak Darurat [USUL]

- `doctor_emergency_contacts`: nama, hubungan, no_hp.
- **[USUL]** `doctor_family_members` hanya bila dibutuhkan (BPJS/asuransi/tunjangan).

## 11. Kesehatan & Kepatuhan [USUL]

- MCU terakhir: tanggal, hasil ringkas (fit/unfit), berlaku sampai, dokumen. Bisa ditarik dari modul MCU rekrutmen (`mcu_*`).
- Status vaksinasi (mis. Hepatitis B), tanggal.
- Pernyataan: kode etik, kerahasiaan, bebas dari sanksi/pelanggaran etik (tanggal + dokumen).
- Riwayat sanksi/pelanggaran etik: tanggal, uraian, tindakan.

## 12. Jejak Rekrutmen (relasi ke modul rekrutmen) [USUL]

Agar asal-usul dokter tidak hilang setelah masuk master:

- `recruitment_application_id` / `candidate_id` (ID lintas DB `recruitment`, tanpa FK).
- Tanggal melamar, tanggal diterima, sumber lamaran, posisi yang dilamar.
- Hasil tahapan: wawancara, tes, MCU, offering (ringkas, rujuk ke DB rekrutmen).

## 13. Notifikasi Masa Berlaku [USUL]

Job terjadwal (Fase 4 menurut komentar kode): kirim pengingat pada H-90 / H-60 / H-30 / H-7 untuk STR, SIP, SK SPK, dan dokumen ber-`expired_at`. Perlu tabel log: `doctor_reminders` (doctor_id, sumber, tanggal_kirim, kanal, status).

## 14. Audit & Akses

- Semua tabel utama sudah soft delete; pertahankan.
- **[USUL]** `doctor_audit_logs`: siapa melihat/mengubah data sensitif (NIK, dokumen), terutama unduhan file.
- Permission panel dokter dipisah dari HRIS (role: admin dokter, komite kredensial, dokter sendiri hanya lihat data miliknya).

---

## Ringkasan Prioritas

| Prioritas | Item |
|---|---|
| Tinggi | Kontak & alamat (§1), jenis dokumen tambahan (§5), pendidikan & pelatihan (§7), jejak rekrutmen (§12) |
| Sedang (pasca-rekrutmen) | SIP per RS (§4), penempatan detail/jadwal (§3), kewenangan klinis & log credentialing (§6), pengalaman kerja (§8), reminder expiry (§13) |
| Rendah / sesuai kebutuhan | Keuangan/honor (§9), keluarga (§10), kesehatan & kepatuhan (§11), audit log (§14) |
