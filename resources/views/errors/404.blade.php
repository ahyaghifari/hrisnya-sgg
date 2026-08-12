<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen relative overflow-hidden" style="font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;">

    <div class="page-deco" aria-hidden="true">
        <div class="deco-a" style="background:#047C54;opacity:0.08;"></div>
        <div class="deco-b" style="background:#042c6c;opacity:0.06;"></div>
        <div class="deco-c" style="border-color:#042c6c;opacity:0.1;"></div>
        <div class="deco-d" style="background:#B9308C;opacity:0.08;"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-5 py-10">
        <div class="w-full max-w-[420px]">

            <!-- Brand -->
            <div class="flex items-center justify-center gap-3 mb-8">
                <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-11 h-11 rounded-xl object-contain bg-white p-1 shadow-sm">
                <div class="leading-tight">
                    <span class="block text-[16px] font-bold text-slate-800">Syifa Global Group</span>
                    <span class="text-[11.5px] text-slate-500">Human Resource Information System</span>
                </div>
            </div>

            <!-- Card -->
            <div class="relative bg-white rounded-[20px] px-9 pt-9 pb-8 shadow-[0_20px_40px_rgba(4,44,108,0.1)] border border-slate-100 overflow-hidden text-center">
                <div class="absolute top-0 left-0 right-0 h-[3px]" style="background:linear-gradient(90deg, #042c6c 0%, #042c6c 55%, #047C54 78%, #B9308C 100%);"></div>

                <div class="text-[84px] font-extrabold leading-none mb-4" style="background:linear-gradient(90deg, #047C54, #042c6c);-webkit-background-clip:text;background-clip:text;color:transparent;">404</div>

                <div class="text-xl font-bold text-slate-800 mb-1">Halaman Tidak Ditemukan</div>
                <div class="text-[13px] text-slate-500 mb-6 leading-relaxed">
                    Halaman yang Anda cari sudah tidak ada, alamatnya berubah, atau memang belum pernah ada. Coba
                    periksa lagi tautannya, atau kembali ke Beranda saja ya.
                </div>

                <a href="{{ url('/') }}"
                    class="w-full py-3 rounded-[10px] text-white text-sm font-semibold flex items-center justify-center gap-2 bg-gradient-to-br from-[#042c6c] to-[#1a56c4] hover:opacity-90 active:translate-y-0 transition">
                    <i class="fas fa-house"></i>
                    Kembali ke Beranda
                </a>
            </div>

            <div class="text-center mt-6 text-xs text-slate-400">
                &copy; {{ date('Y') }} Syifa Global Group &mdash; Seluruh hak dilindungi
            </div>
        </div>
    </div>

</body>
</html>
