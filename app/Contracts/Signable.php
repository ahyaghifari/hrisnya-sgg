<?php

namespace App\Contracts;

/**
 * Kontrak kecil tiap model dokumen yang mau ikut TTE + QR internal (Fase 1,
 * docs/2026-09-28-tte-qr-internal-fase-1.md §2) - dipasangkan dengan trait
 * App\Models\Concerns\HasDocumentSignatures. Implement ini + trait itu, jenis dokumen baru
 * langsung dapat registry/tanda tangan/kode verifikasi tanpa bongkar skema.
 */
interface Signable
{
    /**
     * Data yang di-hash - field yang relevan buat isi dokumen FINAL, bukan kolom teknis (id,
     * timestamps, dst). Urutan key harus stabil (array asosiatif eksplisit, bukan hasil query
     * yang urutannya bisa berubah) supaya json_encode konsisten tiap dipanggil - hash yang
     * dihitung ulang saat verifikasi harus selalu sama kalau datanya memang belum berubah.
     *
     * @return array<string, mixed>
     */
    public function signablePayload(): array;

    public function documentNumber(): string;

    public function documentTypeLabel(): string;

    public function documentType(): \App\Enum\DocumentType;

    public function isRevoked(): bool;

    public function revokedReason(): ?string;

    /**
     * Peran penanda tangan yang DIHARAPKAN ada untuk dokumen ini (bukan yang sudah menandatangani -
     * itu dibaca dari document_signatures). Dipakai halaman verifikasi & tanda tangan buat tahu
     * apa yang masih ditunggu (Pasal 59 ayat 3 PP 71/2019 - persetujuan tiap penanda tangan harus
     * eksplisit, tidak cukup salah satu peran menandatangani lalu dianggap semua selesai).
     *
     * @return array<string, string> role key => label tampil
     */
    public function expectedSignerRoles(): array;

    /**
     * Siapa yang DITUGASKAN mengisi tiap peran (bukan yang SUDAH menandatangani - itu di
     * document_signatures). Dipakai App\Services\Document\DocumentSigningService untuk (1)
     * menemukan dokumen mana saja yang menunggu tanda tangan seorang user, generik lintas jenis
     * dokumen (SP, Berita Acara, nanti Surat Tugas dst - tidak ada kode khusus per jenis di sisi
     * pencarian/modal tanda tangan), dan (2) memastikan hanya orang yang memang ditugaskan yang
     * bisa menandatangani peran itu.
     *
     * @return array<string, ?\App\Models\User> role key => user yang ditugaskan (null kalau
     *         peran itu belum diisi siapa pun)
     */
    public function assignedSigners(): array;
}
