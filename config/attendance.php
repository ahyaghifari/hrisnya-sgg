<?php

return [
    /**
     * NIP yang dikecualikan dari rekap/penarikan data presensi (mis. Direktur, Super Admin —
     * jabatan yang memang tidak diterapkan sistem presensi). Bukan soal enforcement/jadwal
     * (itu urusan schedule_id kosong), murni soal "jangan muncul di rekap & laporan presensi".
     * Daftar pendek & jarang berubah, jadi cukup diedit langsung di sini kalau ada perubahan.
     */
    'excluded_nips' => [
        '2025122401828', // presiden direktur
        '01122024696', // sekretaris direktur
        '2025102100121', // Super Admin
        '2025122401788', // Super Admin
    ],
];
