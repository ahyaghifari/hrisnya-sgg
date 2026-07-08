<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Syifa Employee Portal</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="site min-h-screen relative overflow-hidden">

    <!-- Background decoration -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
        <div class="absolute -top-52 -right-52 w-[640px] h-[640px] rounded-full bg-[#042c6c] opacity-5"></div>
        <div class="absolute -bottom-36 -left-36 w-[480px] h-[480px] rounded-full bg-[#042c6c] opacity-[0.035]"></div>
        <div class="absolute top-2/5 left-[5%] w-60 h-60 rounded-full border-[30px] border-[#042c6c] opacity-[0.03]"></div>
        <div class="absolute bottom-1/5 right-[8%] w-24 h-24 rounded-2xl bg-[#1a56c4] opacity-[0.04] rotate-[25deg]"></div>
    </div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-5 py-10">
        <div class="w-full max-w-[420px]">

            <!-- Brand -->
            <div class="flex items-center justify-center gap-3 mb-8">
                <div class="w-11 h-11 rounded-xl bg-[#042c6c] flex items-center justify-center text-white font-extrabold text-[15px] tracking-tight">SG</div>
                <div class="leading-tight">
                    <span class="block text-[16px] font-bold text-slate-800">Syifa Global Group</span>
                    <span class="text-[11.5px] text-slate-500">Human Resource Information System</span>
                </div>
            </div>

            <!-- Card -->
            <div class="relative bg-white rounded-[20px] px-9 pt-9 pb-8 shadow-[0_4px_6px_rgba(4,44,108,0.04),0_1px_3px_rgba(0,0,0,0.06)] border border-slate-200/70 overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-[#042c6c] to-[#1a56c4]"></div>

                <div class="text-xl font-bold text-slate-800 mb-1">Selamat Datang</div>
                <div class="text-[13px] text-slate-500 mb-7">Masuk untuk mengakses portal</div>

                @if ($errors->any())
                    <div class="flex items-center gap-2.5 bg-red-50 border border-red-200 rounded-[10px] px-3.5 py-2.5 mb-5 text-[13px] text-red-600">
                        <i class="fas fa-circle-exclamation"></i>
                        {{ $errors->first('email') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST">
                    @csrf

                    <div class="mb-4.5">
                        <label class="block text-[13px] font-semibold text-slate-700 mb-1.5" for="email">Email</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[13px] pointer-events-none"></i>
                            <input
                                class="block w-full rounded-[10px] border-slate-200 bg-slate-50 pl-9 pr-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#042c6c] focus:bg-white focus:ring-4 focus:ring-[#042c6c]/[0.07]"
                                type="email" id="email" name="email" value="{{ old('email') }}"
                                placeholder="nama@syifaglobal.com" autocomplete="email" required autofocus>
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
