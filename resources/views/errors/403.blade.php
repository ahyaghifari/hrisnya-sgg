<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen relative overflow-hidden" style="font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg, #042c6c 0%, #0b4f8f 38%, #047C54 72%, #B9308C 100%);">

    <div class="page-deco" aria-hidden="true">
        <div class="deco-a" style="background:#fff;opacity:0.07;"></div>
        <div class="deco-b" style="background:#fff;opacity:0.05;"></div>
        <div class="deco-c" style="border-color:#fff;opacity:0.08;"></div>
        <div class="deco-d" style="background:#fff;opacity:0.12;"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-5 py-10">
        <div class="w-full max-w-[420px]">

            <!-- Brand -->
            <div class="flex items-center justify-center gap-3 mb-8">
                <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-11 h-11 rounded-xl object-contain bg-white/90 p-1">
                <div class="leading-tight">
                    <span class="block text-[16px] font-bold text-white">Syifa Global Group</span>
                    <span class="text-[11.5px] text-white/75">Human Resource Information System</span>
                </div>
            </div>

            <!-- Card -->
            <div class="relative bg-white rounded-[20px] px-9 pt-9 pb-8 shadow-[0_20px_40px_rgba(4,44,108,0.25)] border border-white/40 overflow-hidden text-center">
                <div class="absolute top-0 left-0 right-0 h-[3px]" style="background:linear-gradient(90deg, #042c6c 0%, #042c6c 55%, #047C54 78%, #B9308C 100%);"></div>

                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5" style="background:linear-gradient(135deg, rgba(4,44,108,0.08), rgba(185,48,140,0.08));">
                    <i class="fas fa-hand-holding-heart text-2xl" style="color:#042c6c;"></i>
                </div>

                <div class="text-xl font-bold text-slate-800 mb-1">Halaman Ini Belum Bisa Diakses</div>
                <div class="text-[13px] text-slate-500 mb-6 leading-relaxed">
                    Akun Anda saat ini belum memiliki izin untuk membuka halaman ini. Kalau menurut Anda ini keliru,
                    jangan ragu untuk menghubungi Admin atau tim HC ya — kami siap membantu.
                </div>

                <a href="{{ url('/') }}"
                    class="w-full py-3 rounded-[10px] text-white text-sm font-semibold flex items-center justify-center gap-2 bg-gradient-to-br from-[#042c6c] to-[#1a56c4] hover:opacity-90 active:translate-y-0 transition">
                    <i class="fas fa-house"></i>
                    Kembali ke Beranda
                </a>
            </div>

            <div class="text-center mt-6 text-xs text-white/70">
                &copy; {{ date('Y') }} Syifa Global Group &mdash; Seluruh hak dilindungi
            </div>
        </div>
    </div>

</body>
</html>
