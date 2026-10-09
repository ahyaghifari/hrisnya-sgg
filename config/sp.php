<?php

return [
    /*
     | Tanda tangan digital (QR) Berita Acara. Ditahan dulu: setelah BA di-FIX, HC mencetak, BA ditandatangani
     | langsung, lalu file bertanda tangan diunggah (SpReportService::uploadSigned). Kodenya tetap ada;
     | set SP_DIGITAL_SIGNING=true untuk mengaktifkan lagi alur TTD digital.
     */
    'digital_signing' => (bool) env('SP_DIGITAL_SIGNING', false),
];