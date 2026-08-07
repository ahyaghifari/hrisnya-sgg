<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="site min-h-screen relative overflow-hidden">

    <div class="page-deco" aria-hidden="true">
        <div class="deco-a"></div><div class="deco-b"></div><div class="deco-c"></div><div class="deco-d"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-5 py-10">
        <div class="w-full max-w-[420px]">

            <!-- Brand -->
            <div class="flex items-center justify-center gap-3 mb-8">
                <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-11 h-11 rounded-xl object-contain">
                <div class="leading-tight">
                    <span class="block text-[16px] font-bold text-slate-800">Syifa Global Group</span>
                    <span class="text-[11.5px] text-slate-500">Human Resource Information System</span>
                </div>
            </div>

            <!-- Card -->
            <div class="relative bg-white rounded-[20px] px-9 pt-9 pb-8 shadow-[0_4px_6px_rgba(4,44,108,0.04),0_1px_3px_rgba(0,0,0,0.06)] border border-slate-200/70 overflow-hidden">
                {{-- Border atas kartu login disamakan dengan border bawah navigasi portal
                (.topnav::after di app.css) — identitas visual yang sama antara halaman login
                dan portal setelah masuk. --}}
                <div class="absolute top-0 left-0 right-0 h-[3px]" style="background:linear-gradient(90deg, #042c6c 0%, #042c6c 55%, #047C54 78%, #B9308C 100%);"></div>

                <div class="text-xl font-bold text-slate-800 mb-1">Selamat Datang</div>
                <div class="text-[11px] text-slate-500 mb-4">Masuk untuk mengakses portal karyawan Syifa Global Group</div>

                @if ($errors->any())
                    <div class="flex items-center gap-2.5 bg-red-50 border border-red-200 rounded-[10px] px-3.5 py-2.5 mb-5 text-[13px] text-red-600">
                        <i class="fas fa-circle-exclamation"></i>
                        {{ $errors->first('email') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 rounded-[10px] px-3.5 py-2.5 mb-5 text-[13px] text-emerald-700">
                        <i class="fas fa-circle-check"></i>
                        {{ session('status') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST">
                    @csrf

                    <div class="mb-4.5">
                        <label class="block text-[13px] font-semibold text-slate-700 mb-1.5" for="email">Email atau NIP</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[13px] pointer-events-none"></i>
                            <input
                                class="block w-full rounded-[10px] border-slate-200 bg-slate-50 pl-9 pr-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#042c6c] focus:bg-white focus:ring-4 focus:ring-[#042c6c]/[0.07]"
                                type="text" id="email" name="email" value="{{ old('email') }}"
                                placeholder="nama@syifaglobal.com atau NIP" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4.5">
                        <label class="block text-[13px] font-semibold text-slate-700 mb-1.5" for="password">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[13px] pointer-events-none"></i>
                            <input
                                class="block w-full rounded-[10px] border-slate-200 bg-slate-50 pl-9 pr-10 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#042c6c] focus:bg-white focus:ring-4 focus:ring-[#042c6c]/[0.07]"
                                type="password" id="password" name="password"
                                placeholder="Masukkan password" autocomplete="current-password" required>
                            <button type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#042c6c] text-[13px] p-1"
                                onclick="const inp=document.getElementById('password'); inp.type = inp.type==='password' ? 'text' : 'password'; this.querySelector('i').className = inp.type==='password' ? 'fas fa-eye' : 'fas fa-eye-slash';">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="remember" name="remember" class="w-[15px] h-[15px] rounded accent-[#042c6c]">
                            <label for="remember" class="text-[13px] text-slate-500">Ingat saya</label>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full py-3 rounded-[10px] text-white text-sm font-semibold flex items-center justify-center gap-2 bg-gradient-to-br from-[#042c6c] to-[#1a56c4] hover:opacity-90 active:translate-y-0 transition">
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        Masuk
                    </button>
                </form>
            </div>

            <div class="text-center mt-6 text-xs text-slate-500">
                &copy; {{ date('Y') }} Syifa Global Group &mdash; Seluruh hak dilindungi
            </div>
        </div>
    </div>

</body>
</html>
