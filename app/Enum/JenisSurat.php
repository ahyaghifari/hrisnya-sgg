<?php

namespace App\Enum;

enum JenisSurat: string
{
    case KEBIJAKAN_PERATURAN = 'A';
    case PEDOMAN_PANDUAN = 'B';
    case EDARAN_PENGUMUMAN = 'C';
    case INTERNAL_MEMO = 'D';
    case SURAT_KEPUTUSAN = 'E';
    case SURAT_PERINTAH = 'F';
    case SURAT_TUGAS = 'G';
    case SURAT_UNDANGAN = 'H';
    case SURAT_KUASA = 'I';
    case BERITA_ACARA = 'J';
    case PERJANJIAN_MOU = 'K';
    case PERMOHONAN_BARANG_JASA = 'L';
    case SURAT_BALASAN = 'M';
    case SURAT_TANDA_TERIMA = 'N';
    case SURAT_PENGANTAR = 'O';
    case SURAT_PERINGATAN = 'SP';
    case SURAT_REKOMENDASI = 'P';
    case MCU = 'MCU';
    case SOP = 'SOP';
    case FORMULIR = 'FRM';
    case SURAT_KETERANGAN = 'Q';
    case PKWT = 'PKWT';
    case PKWTT = 'PKWTT';
    case CERT = 'CERT';
    case SPKK = 'SPKK';
    case SKK = 'SKK';

    public function label(): string
    {
        return match ($this) {
            self::KEBIJAKAN_PERATURAN => 'Kebijakan / Peraturan / PP',
            self::PEDOMAN_PANDUAN => 'Pedoman / Panduan Pelaksanaan',
            self::EDARAN_PENGUMUMAN => 'Surat Edaran / Pengumuman',
            self::INTERNAL_MEMO => 'Internal Memo',
            self::SURAT_KEPUTUSAN => 'Surat Keputusan',
            self::SURAT_PERINTAH => 'Surat Perintah',
            self::SURAT_TUGAS => 'Surat Tugas',
            self::SURAT_UNDANGAN => 'Surat Undangan',
            self::SURAT_KUASA => 'Surat Kuasa',
            self::BERITA_ACARA => 'Berita Acara',
            self::PERJANJIAN_MOU => 'Surat Perjanjian / MoU',
            self::PERMOHONAN_BARANG_JASA => 'Surat Permohonan Barang / Jasa',
            self::SURAT_BALASAN => 'Surat Balasan',
            self::SURAT_TANDA_TERIMA => 'Surat Tanda Terima',
            self::SURAT_PENGANTAR => 'Surat Pengantar',
            self::SURAT_PERINGATAN => 'Surat Peringatan',
            self::SURAT_REKOMENDASI => 'Surat Rekomendasi',
            self::MCU => 'Surat MCU',
            self::SOP => 'Standard Operational Procedure',
            self::FORMULIR => 'Formulir',
            self::SURAT_KETERANGAN => 'Surat Keterangan',
            self::PKWT => 'Perjanjian Kerja Waktu Tertentu',
            self::PKWTT => 'Perjanjian Kerja Waktu Tidak Tentu',
            self::CERT => 'Sertifikat',
            self::SPKK => 'Surat Penugasan Kerja Klinik',
            self::SKK => 'Surat Keterangan Kesehatan',
        };
    }

    /** Warna label jenis: hue unik per case (urutan enum), pastel untuk latar + gelap untuk teks. Urutan case jangan diacak
     * kalau warna sudah dipakai orang — hue ikut bergeser. */
    public function warna(): array
    {
        $hue = (int) round(array_search($this, self::cases(), true) * 360 / count(self::cases()));

        return ['bg' => "hsl({$hue} 70% 90%)", 'text' => "hsl({$hue} 60% 26%)"];
    }

    /** Semua jenis surat keluar - belum ada jenis yang masuk kategori 'masuk' (lihat
     * docs/PENOMORAN-SURAT.md §4, surat masuk gak dikategorikan lewat jenis surat). Tambah
     * case di match ini kalau nanti ada jenis yang perlu tipe 'masuk'. */
    public function tipeSurat(): string
    {
        return match ($this) {
            default => 'keluar',
        };
    }
}
