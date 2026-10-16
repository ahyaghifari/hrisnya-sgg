<div>
@php
    use App\Enum\Task\TaskStatus;
    use App\Enum\Task\TaskPriority;
    // Todo Saya (self-assigned) lewatin REVIEW - langsung IN_PROGRESS -> COMPLETE.
    $isTodoHero = ! $activeProjectId && $scope === 'todo';
    $boardStatuses = $isTodoHero ? TaskStatus::BOARD_ORDER_SELF_ASSIGNED : TaskStatus::BOARD_ORDER;
    // Gaya "hero" (gradient + shape dekoratif punya sistem, lihat .page-hero/.a-deco-* di
    // app.css) dipakai di board pribadi (Todo Saya & Tugas Saya) - warna dari status-nya
    // sendiri (bukan index loop) biar Review konsisten dapat gold-dark di board manapun dia
    // muncul. Review = emas gelap ala aksen gold-shiny halaman profil, biar "butuh perhatian".
    $useBoardHero = $isTodoHero || (! $activeProjectId && $scope === 'saya');
    // Individu/Tim potensial banyak baris (banyak staff x banyak tugas) - tabel lebih gampang
    // di-scan/sortir daripada card yang makan tempat vertikal, beda kebutuhan sama Todo/Tugas Saya.
    $isTableScope = ! $activeProjectId && in_array($scope, ['individu', 'tim'], true);
    $heroClassFor = fn (TaskStatus $status) => match ($status) {
        TaskStatus::REVIEW => 'gold-dark',
        TaskStatus::IN_PROGRESS => 'gold',
        TaskStatus::COMPLETE => 'green',
        default => 'navy',
    };
    // Task::due_date sengaja tidak di-cast ke date di model (lihat komentar di Task.php) — parse
    // manual di sini, bukan panggil ->translatedFormat() langsung di atas string mentah.
    $fmtDate = fn ($d, $f = 'j M') => $d ? \Carbon\Carbon::parse($d)->translatedFormat($f) : null;
    // Warna tenggat di kartu: lewat = hitam/teks putih, hari H = merah, H-2..H-1 = oranye. Tugas Selesai tanpa warna.
    $dueStyle = function ($task) {
        if (! $task->due_date || $task->status === TaskStatus::COMPLETE->value || $task->is_done) return '';
        $due = \Carbon\Carbon::parse($task->due_date);
        $days = now()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);
        $base = 'padding:1px 6px;border-radius:4px;';
        return match (true) {
            $due->isPast() => $base . 'background:#000;color:#fff;',
            $days <= 0 => $base . 'background:#dc2626;color:#fff;',
            $days <= 2 => $base . 'background:#f97316;color:#fff;',
            default => '',
        };
    };
    // Map getColor() (gray/warning/info/success/danger) ke class .badge-* yang ada di app.css.
    $badgeClass = fn (string $color) => match ($color) {
        'warning' => 'badge-pending',
        'info' => 'badge-info',
        'success' => 'badge-approved',
        'danger' => 'badge-rejected',
        default => 'badge-cancelled',
    };
    // Aksen warna kartu tugas (border kiri + dot prioritas) — dari getColor() yang sama, bukan
    // pill baru. Hex literal di sini karena dipakai lewat inline CSS var, bukan class .badge-*.
    $priorityAccent = fn (string $color) => match ($color) {
        'warning' => '#a16207',
        'info' => '#1a56c4',
        'success' => '#15803d',
        'danger' => '#b91c1c',
        default => '#64748b',
    };
@endphp

<style>
    /* Kartu tugas: border kiri = warna prioritas (--accent), hover ngangkat dikit biar kerasa
    "bisa diklik/diseret" tanpa perlu badge warna-warni penuh di dalamnya. */
    .task-card { position: relative; background: var(--surface-alt); border: 1px solid var(--border); border-left: 3px solid var(--accent, var(--border)); border-radius: 10px; padding: 10px 12px; cursor: pointer; box-shadow: 0 1px 2px rgba(15,23,42,0.05); transition: box-shadow 0.15s ease, transform 0.15s ease; }
    [data-status-list] .task-card, [data-list-id] .task-card:not(.task-card-locked) { cursor: grab; }
    [data-status-list] .task-card:active, [data-list-id] .task-card:not(.task-card-locked):active, .task-card.sortable-chosen { cursor: grabbing; }
    .task-card.sortable-ghost { opacity: 0.4; }
    /* Kartu yang bisa di-drag: cegah seleksi teks/menu tekan-lama bawaan HP saat kartu ditahan, dan
    salinan yang ikut jari (ghost, ditempel di body) dibuat jelas terlihat di atas segalanya. */
    [data-status-list] .task-card:not(.task-card-locked), [data-list-id] .task-card:not(.task-card-locked) { -webkit-touch-callout: none; -webkit-user-select: none; user-select: none; touch-action: manipulation; }
    /* Salinan yang ikut kursor digerakkan SortableJS lewat inline transform tiap mousemove - transition
    bawaan .task-card (transform 0.15s) bikin salinannya tertinggal jauh di belakang kursor & terasa
    "berat", jadi WAJIB tanpa transition. */
    .task-card.sortable-fallback { opacity: 0.95 !important; box-shadow: 0 14px 30px rgba(15,23,42,0.35); transform-origin: center; z-index: 10005 !important; pointer-events: none; transition: none !important; will-change: transform; }
    .task-card:hover { box-shadow: 0 8px 20px rgba(15,23,42,0.12); transform: translateY(-1px); }
    html.dark .task-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.35); }
    :root { --new-item-bg: rgba(22,163,74,.12); }
    html.dark { --new-item-bg: rgba(74,222,128,.14); }
    .task-card-top { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 7px; }
    .task-card-title { font-size: 13px; font-weight: 600; color: var(--text-heading); line-height: 1.4; margin: 0; flex: 1; min-width: 0; }
    .task-card-avatar { flex-shrink: 0; margin-top: 1px; }
    .task-card-avatars { display: inline-flex; align-items: center; padding-left: 5px; }
    .task-card-avatars > * { margin-left: -5px; box-shadow: 0 0 0 2px var(--surface); }
    .task-card-avatars-more { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 99px; background: var(--border-soft); color: var(--text-muted); font-size: 10px; font-weight: 800; }
    .task-card-meta { display: flex; flex-wrap: wrap; row-gap: 4px; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; color: var(--text-muted); margin-bottom: 6px; }
    .task-card-priority { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: var(--accent); }
    .task-card-priority .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }
    .task-card-assignee { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-muted); }
    .task-card-footer { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-soft); font-size: 11px; color: var(--text-muted); }

    /* Card di List custom yang sudah dikasih warna (background diisi lewat --accent di
    Blade) - teks & border dalam kartu dipaksa putih/transparan biar tetap kebaca di atas
    warna solid apapun, bukan cuma tint tipis. */
    .task-card.is-tinted { background: var(--accent) !important; border-left-color: rgba(255,255,255,0.4) !important; }
    .task-card.is-tinted .task-card-title,
    .task-card.is-tinted .task-card-priority,
    .task-card.is-tinted .task-card-priority .dot { color: #fff; }
    .task-card.is-tinted .task-card-priority .dot { background: #fff; }
    .task-card.is-tinted .task-card-meta,
    .task-card.is-tinted .task-card-assignee,
    .task-card.is-tinted .task-card-footer { color: rgba(255,255,255,0.85); }
    .task-card.is-tinted .task-card-footer { border-top-color: rgba(255,255,255,0.3); }
    .task-card.is-tinted select.form-input { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.35); color: #fff; }

    /* Jumlah kolom/section (List custom atau status Todo/Proses/dst) ganjil = 3 kolom per baris,
    genap = 2 kolom (--cols diisi dari Blade). Di layar sempit dipaksa 1 kolom - grid banyak
    kolom bikin section kesempitan buat dibaca di HP. */
    .task-board-grid { display: grid; grid-template-columns: repeat(var(--cols, 1), 1fr); gap: 20px; align-items: start; align-content: start; min-height: 85vh; max-height: 85vh; overflow-y: auto; }
    /* Tugas Saya: 4 kolom status selalu berdampingan (ala Trello), sisanya scroll ke kanan -
    di desktop juga, bukan cuma HP. Lebar kolom di HP diatur aturan mobile di bawah. */
    .task-board-grid.task-board-scroll { display: flex; align-items: flex-start; overflow-x: auto; }
    @media (min-width: 768px) { .task-board-grid.task-board-scroll > * { flex: 0 0 330px; min-width: 0; } }
    .task-board-grid > * { box-shadow: 0 6px 18px rgba(15,23,42,0.14), 0 2px 4px rgba(15,23,42,0.08); }
    html.dark .task-board-grid > * { box-shadow: 0 6px 18px rgba(0,0,0,0.45), 0 2px 4px rgba(0,0,0,0.3); }
    /* HP/tablet kecil (<768px, sama dengan breakpoint bottom nav): kolom berdampingan, sisanya scroll ke kanan. */
    @media (max-width: 767.98px) {
        .task-board-grid { display: flex; align-items: flex-start; overflow-x: auto; gap: 16px; -webkit-overflow-scrolling: touch; }
        .task-board-grid > * { flex: 0 0 260px; min-width: 0; }
    }

    /* Gaya "hero" khusus Todo Saya: gradient sama seperti .page-hero yang sudah ada di
    app.css (bukan warna baru), plus shape dekoratif .a-deco-* yang sama dipakai di
    welcome-card/absen-card. Card di dalamnya jadi kotak putih polos ala mockup Trello. */
    .task-hero-column { position: relative; overflow: hidden; border-radius: 20px; padding: 16px 14px; color: #fff; }
    .task-hero-column.navy { background: linear-gradient(130deg, var(--brand-fill) 0%, color-mix(in srgb, var(--brand-fill), #ffffff 22%) 100%); }
    .task-hero-column.gold { background: linear-gradient(130deg, #92400e 0%, #d97706 100%); }
    .task-hero-column.green { background: linear-gradient(130deg, #047C54 0%, #024732 100%); }
    /* Sama seperti gradient aktif tab gold-shiny di halaman profil (#f5c518 -> #d4a017),
    cuma digelapin dikit biar kebaca beda dari aksen "profil saya" itu sendiri. */
    .task-hero-column.gold-dark { background: linear-gradient(135deg, #d4a017 0%, #92720a 100%); }
    /* Angka jumlah tugas belum selesai di pill nav "Tugas Saya" - warna ngikut teks tombolnya
    sendiri (currentColor) jadi otomatis kontras di kondisi aktif/nonaktif gold-shiny. */
    /* Baris list Penugasan Individu/Tim Saya - pengganti tabel di HP (kolom nggak muat). */
    .task-list-row { display: flex; align-items: center; gap: 14px; padding: 12px 16px; cursor: pointer; border-bottom: 1px solid var(--border-soft); transition: background 0.12s; }
    .task-list-row:last-child { border-bottom: none; }
    .task-list-row:hover { background: var(--surface-alt); }
    .task-view-mobile { display: none; }
    /* Tombol "Tugas Baru" diperbesar cuma di desktop, mobile tetap ukuran .btn-sm. */
    .btn-secondary.task-back-btn { background: #cbd5e1; color: #334155; }
    .btn-secondary.task-back-btn:hover { background: #b6c2d1; color: #1e293b; box-shadow: 0 4px 14px rgba(100,116,139,0.25); }
    html.dark .btn-secondary.task-back-btn { background: #334155; color: #e2e8f0; }
    html.dark .btn-secondary.task-back-btn:hover { background: #475569; color: #fff; box-shadow: 0 4px 14px rgba(0,0,0,0.35); }
    @media (min-width: 768px) {
        .task-new-btn { padding: 11px 22px !important; font-size: 14.5px !important; border-radius: 10px !important; }
        .task-new-btn i { font-size: 14px !important; }
    }
    /* Mobile (<768px, sama dengan breakpoint .pwa-bottom-nav): "Tugas Baru" jadi FAB bulat
    melayang kanan-bawah, di atas bottom nav (tinggi 60px + safe area). Label disembunyikan. */
    /* Zoom papan cuma di mobile (--z diisi Alpine dari tombol zoom); desktop nggak kena. min-height
    dibagi --z karena CSS zoom ikut menskalakan panjang (75vh * z), biar tetap 75vh di layar. */
    .task-zoom-fab { display: none; }
    /* Tab filter Board (Semua / Tugas Saya). */
    .task-filter-tabs { display: inline-flex; padding: 3px; gap: 2px; border-radius: 99px; background: var(--surface-alt); border: 1px solid var(--border); }
    .task-filter-tabs button { border: none; background: none; padding: 5px 14px; border-radius: 99px; font-family: inherit; font-size: 12.5px; font-weight: 700; color: var(--text-muted); cursor: pointer; }
    .task-filter-tabs button.is-active { background: var(--brand-fill); color: #fff; }
    /* Desktop: zoom cuma untuk Board (project) - papan status Todo/Tugas Saya tetap tanpa zoom.
    Kontrolnya inline di atas papan (bukan melayang), rata kanan. */
    @media (min-width: 768px) {
        .task-board-grid.task-board-zoomable { zoom: var(--z, 1); min-height: calc(85vh / var(--z, 1)); max-height: calc(85vh / var(--z, 1)); transition: zoom 0.25s ease, min-height 0.25s ease, max-height 0.25s ease; }
        .task-zoom-fab.is-board { display: flex; align-items: center; width: fit-content; margin: 0 0 8px auto; height: 36px; border-radius: 99px; background: var(--surface); border: 1px solid var(--border); overflow: hidden; }
        .task-zoom-fab.is-board button { border: none; background: none; color: var(--text-heading); height: 100%; padding: 0 12px; font-size: 13px; font-weight: 700; font-family: inherit; cursor: pointer; }
        .task-zoom-fab.is-board button:hover:not(:disabled) { background: var(--surface-alt); }
        .task-zoom-fab.is-board button:disabled { opacity: 0.35; cursor: not-allowed; }
    }
    @media (max-width: 767.98px) {
        .task-board-grid { zoom: var(--z, 1); min-height: calc(75vh / var(--z, 1)); max-height: calc(75vh / var(--z, 1)); transition: zoom 0.25s ease, min-height 0.25s ease, max-height 0.25s ease; }
        /* Melayang di TENGAH bawah (instruksi user 2026-09-20), sejajar FAB "Tugas Baru" di kanan
        (.task-fab: bottom 60px+safe+16px, tinggi 56px) - tidak menumpuk di atasnya. */
        .task-zoom-fab { display: flex; position: fixed; left: 50%; transform: translateX(-50%); bottom: calc(60px + env(safe-area-inset-bottom) + 22px); z-index: 200; align-items: center; height: 44px; border-radius: 99px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 6px 18px rgba(15,23,42,0.18); overflow: hidden; }
        .task-zoom-fab button { border: none; background: none; color: var(--text-heading); height: 100%; padding: 0 12px; font-size: 13px; font-weight: 700; font-family: inherit; cursor: pointer; }
        .task-zoom-fab button:disabled { opacity: 0.35; cursor: not-allowed; }
        .task-zoom-fab button:active:not(:disabled) { background: var(--surface-alt); }
    }
    @media (max-width: 767.98px) {
        .task-fab { position: fixed; right: 16px; bottom: calc(60px + env(safe-area-inset-bottom) + 16px); z-index: 200; width: 56px; height: 56px; padding: 0 !important; border-radius: 50% !important; box-shadow: 0 6px 18px rgba(4,44,108,0.35); }
        .task-fab .task-fab-label { display: none; }
        .task-fab i { font-size: 20px !important; margin: 0; }
    }
    @media (max-width: 639.98px) {
        .task-view-desktop { display: none; }
        .task-view-mobile { display: block; }
    }
    /* Sama persis gradient aktif pill gold-shiny (Tugas Saya/Profil Saya) - dipakai juga di
    tombol Review biar aksi "menuju approval" konsisten kelihatan sama-sama emas. */
    .btn-gold { background: linear-gradient(135deg, #fde68a 0%, #f5c518 45%, #d4a017 100%); color: #4a3403; border: none; }
    .btn-gold:hover { filter: brightness(0.96); }
    .task-hero-column-header { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 14px; font-weight: 700; font-size: 14px; }
    .task-hero-column-header .count-badge { background: rgba(255,255,255,0.25); color: #fff; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 99px; }
    /* Kartu putih di dalam kolom hero: 2 kolom pas kolomnya cukup lebar, 3 kolom kalau beneran
    lebar (bukan patokan viewport - container query biar ngikutin lebar kolom asli, soalnya
    kolom yang sama bisa sempit/lebar tergantung board-nya 2 atau 3 kolom di layar segitu). */
    /* Preset warna List Board (TaskList::COLOR_PRESETS): 4 pertama sama dengan kolom status
    (Biru/Proses/Review/Selesai), sisanya warna sistem (pink = header MyKanban, teal & sky =
    ikon grid menu). .task-swatch = bulatan pilihan warna di menu List / form List baru. */
    .task-hero-column.pink, .task-swatch.pink { background: linear-gradient(130deg, #B9308C 0%, #8a2269 100%); }
    .task-hero-column.teal, .task-swatch.teal { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); }
    .task-hero-column.sky, .task-swatch.sky { background: linear-gradient(130deg, #0ea5e9 0%, #0369a1 100%); }
    .task-hero-column.red, .task-swatch.red { background: linear-gradient(130deg, #dc2626 0%, #991b1b 100%); }
    .task-hero-column.purple, .task-swatch.purple { background: linear-gradient(130deg, #7c3aed 0%, #5b21b6 100%); }
    .task-hero-column.indigo, .task-swatch.indigo { background: linear-gradient(130deg, #4f46e5 0%, #3730a3 100%); }
    .task-hero-column.orange, .task-swatch.orange { background: linear-gradient(130deg, #ea580c 0%, #9a3412 100%); }
    .task-hero-column.rose, .task-swatch.rose { background: linear-gradient(130deg, #e11d48 0%, #9f1239 100%); }
    .task-hero-column.lime, .task-swatch.lime { background: linear-gradient(130deg, #4d7c0f 0%, #365314 100%); }
    .task-hero-column.slate, .task-swatch.slate { background: linear-gradient(130deg, #475569 0%, #1e293b 100%); }
    .task-swatch.navy { background: linear-gradient(130deg, var(--brand-fill) 0%, color-mix(in srgb, var(--brand-fill), #ffffff 22%) 100%); }
    .task-swatch.gold { background: linear-gradient(130deg, #92400e 0%, #d97706 100%); }
    .task-swatch.gold-dark { background: linear-gradient(135deg, #d4a017 0%, #92720a 100%); }
    .task-swatch.green { background: linear-gradient(130deg, #047C54 0%, #024732 100%); }
    .task-swatch { width: 24px; height: 24px; padding: 0; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--border); cursor: pointer; flex-shrink: 0; transition: transform 0.12s ease; }
    .task-swatch:hover { transform: scale(1.15); }
    .task-swatch.is-selected { box-shadow: 0 0 0 2px var(--brand-fill); }
    .task-hero-column { container-type: inline-size; }
    /* Kolom List Board: overflow visible (dropdown menu nggak boleh terpotong), shape dekoratif
    dipotong sendiri oleh pembungkusnya. */
    .task-hero-column.task-list-column { overflow: visible; }
    /* Menu ⋮ (dropdown hover): tanpa jarak dari tombolnya (default ada celah 10px yang bikin hover
    putus sebelum kursor sampai ke menu), di atas isi kolom (body z-index sama = nimpa menu), dan
    nggak wrap teksnya. */
    .task-list-column .task-hero-column-header { z-index: 3; }
    /* Area drop selalu cukup tinggi walau List kosong. */
    .task-list-column .task-hero-column-body { min-height: 96px; align-content: start; }
    .task-list-column .dropdown-menu { top: 100%; min-width: 210px; }
    .task-list-column .dropdown-menu .dropdown-item { white-space: nowrap; }
    /* Tombol ikon pindah List di footer kartu - satu baris dengan ikon lampiran & komentar, rata kanan. */
    .task-card-move { position: relative; margin-left: auto; }
    .task-card-move > button { border: none; background: none; padding: 0 2px; color: inherit; font-size: inherit; cursor: pointer; opacity: 0.75; }
    .task-card-move > button:hover { opacity: 1; }
    .task-card-move-menu { position: absolute; right: 0; bottom: calc(100% + 6px); z-index: 30; min-width: 150px; padding: 6px; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 8px 24px rgba(15,23,42,0.18); display: flex; flex-direction: column; }
    .task-card-move-menu button { border: none; background: none; text-align: left; padding: 7px 10px; border-radius: 8px; font-family: inherit; font-size: 12px; font-weight: 600; color: var(--text-heading); cursor: pointer; white-space: nowrap; }
    .task-card-move-menu button:hover { background: var(--surface-alt); }
    .task-lightbox { position: fixed; inset: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgba(0,0,0,0.88); }
    .task-lightbox img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
    .task-lightbox-close { position: absolute; top: 14px; right: 14px; width: 38px; height: 38px; border: none; border-radius: 50%; background: rgba(255,255,255,0.16); color: #fff; font-size: 16px; cursor: pointer; }
    .task-lightbox-close:hover { background: rgba(255,255,255,0.28); }
    .task-list-add-btn { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; margin-top: 10px; padding: 9px 12px; border: 1.5px dashed rgba(255,255,255,0.55); border-radius: 12px; background: rgba(255,255,255,0.12); color: #fff; font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: background 0.15s ease; }
    .task-list-add-btn:hover { background: rgba(255,255,255,0.24); }
    .task-hero-deco-clip { position: absolute; inset: 0; overflow: hidden; border-radius: inherit; pointer-events: none; }
    .task-hero-column-body { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr; gap: 10px; align-items: start; }
    @container (min-width: 360px) { .task-hero-column-body { grid-template-columns: repeat(2, 1fr); } }
    @container (min-width: 560px) { .task-hero-column-body { grid-template-columns: repeat(3, 1fr); } }
    .task-hero-column .task-card { background: #fff; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.18); }
    .task-hero-column .task-card-title { color: #1e293b; }
    .task-hero-column .task-card-meta, .task-hero-column .task-card-assignee, .task-hero-column .task-card-footer { color: #64748b; }
    .task-hero-column .task-card-footer { border-top-color: #e2e8f0; }
    /* Kartu di kolom hero SELALU putih (termasuk dark mode), jadi tombol di dalamnya dikunci ke
    palet terang juga - kalau ngikut token tema, hover di dark mode jadi latar gelap + teks gelap. */
    .task-hero-column .task-card .btn-ghost { color: #1e293b; background: transparent; border-color: #cbd5e1; }
    .task-hero-column .task-card .btn-ghost:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
    .task-hero-column .task-card select.form-input { background: #f8fafc; border-color: #e2e8f0; color: #1e293b; }
    .task-hero-column .task-card p[style*="italic"] { color: #64748b !important; }
    .task-hero-column-body > p { color: rgba(255,255,255,0.75) !important; }

    /* Modal Task: overlay jangan ikut scroll (dobel scrollbar keliatan aneh), cukup .modal-box
    sendiri yang scroll di dalam container-nya. Modal detail dilebarin (2026-08-02), berlaku
    buat semua jenis task ke depannya karena dipakai bareng di 1 modal detail generik. */
    .modal-overlay { overflow-y: hidden; }
    /* Scrollbar tipis, bulat, ngikutin token tema (otomatis benar di dark mode): papan
    (scroll horizontal di HP), modal, dan tabel/riwayat yang overflow. */
    /* scrollbar-width/color standar sengaja cuma buat browser tanpa ::-webkit-scrollbar (Firefox) -
    di Chrome/Safari properti standar menimpa ::-webkit-scrollbar & ukuran custom jadi diabaikan. */
    @supports not selector(::-webkit-scrollbar) { .task-board-grid, .modal-box, .task-view-desktop, .task-list-scroll { scrollbar-width: auto; scrollbar-color: var(--text-faint) transparent; } }
    .task-board-grid::-webkit-scrollbar, .modal-box::-webkit-scrollbar, .task-view-desktop::-webkit-scrollbar, .task-list-scroll::-webkit-scrollbar { width: 10px; height: 10px; }
    .task-board-grid::-webkit-scrollbar-track, .modal-box::-webkit-scrollbar-track, .task-view-desktop::-webkit-scrollbar-track, .task-list-scroll::-webkit-scrollbar-track { background: transparent; }
    .task-board-grid::-webkit-scrollbar-thumb, .modal-box::-webkit-scrollbar-thumb, .task-view-desktop::-webkit-scrollbar-thumb, .task-list-scroll::-webkit-scrollbar-thumb { background: var(--text-faint); border-radius: 99px; }
    .task-board-grid::-webkit-scrollbar-thumb:hover, .modal-box::-webkit-scrollbar-thumb:hover, .task-view-desktop::-webkit-scrollbar-thumb:hover, .task-list-scroll::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
    /* Transisi buka modal (tambah/edit/detail task, anggota): modal dirender kondisional jadi
    elemennya baru masuk DOM tiap dibuka - animasi CSS jalan otomatis, tanpa JS. Desktop: overlay
    fade + kotak naik sambil membesar dikit. HP (modal layar penuh): kotak slide dari bawah. */
    @keyframes task-modal-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes task-modal-pop { from { opacity: 0; transform: translateY(14px) scale(0.97); } to { opacity: 1; transform: none; } }
    @keyframes task-modal-slide { from { transform: translateY(100%); } to { transform: none; } }
    .modal-overlay .modal-box { border-radius: 20px; }
    .modal-overlay { animation: task-modal-fade 0.18s ease-out; }
    .modal-overlay > .modal-box { animation: task-modal-pop 0.22s cubic-bezier(0.2, 0.8, 0.2, 1); }
    @media (max-width: 767.98px) {
        .modal-overlay > .modal-box { animation: task-modal-slide 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); }
    }
    @media (prefers-reduced-motion: reduce) {
        .modal-overlay, .modal-overlay > .modal-box { animation: none; }
    }
    .task-detail-modal-wide { max-width: 1120px; }
    .task-create-modal-wide { max-width: 640px; }
    .task-checklist-checkbox.custom-checkbox { width: 18px; height: 18px; }
    .task-checklist-checkbox .box { border-radius: 5px; }
    .task-checklist-checkbox .box i { font-size: 10px; }
    /* Baris checklist yang bisa digeser: kotak berborder + bayangan tipis supaya kelihatan "bisa diangkat". */
    .task-checklist-row.is-draggable { padding: 7px 10px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface, #fff); box-shadow: 0 1px 2px rgba(15,23,42,0.06); transition: box-shadow .15s, border-color .15s; }
    .task-checklist-row.is-draggable:hover { border-color: var(--border); box-shadow: 0 3px 8px rgba(15,23,42,0.12); }
    .task-checklist-row.sortable-chosen { box-shadow: 0 8px 18px rgba(15,23,42,0.22); cursor: grabbing; }
    .task-checklist-row.sortable-ghost { opacity: 0.4; }
    .task-checklist-row.sortable-fallback { opacity: 0.95 !important; z-index: 10005 !important; pointer-events: none; }

    /* Modal detail task di HP: layar penuh (bukan kotak ngambang kekecilan), 2 kolom
    (isi task + komentar) ditumpuk vertikal, komentar dapat garis atas bukan garis kiri. */
    @media (max-width: 767.98px) {
        .task-detail-modal-wide,
        .task-create-modal-wide {
            max-width: 100%;
            width: 100%;
            height: 100%;
            max-height: 100%;
            margin: 0;
            border-radius: 0;
        }
        .task-detail-body { flex-direction: column !important; gap: 20px !important; }
        /* Modal buat/detail tugas di HP: font & komponen diperkecil (versi desktop kebesaran di layar sempit). */
        .task-detail-modal-wide, .task-create-modal-wide { padding: 16px 14px; }
        .task-detail-modal-wide h3 { font-size: 16px !important; line-height: 1.3 !important; }
        .task-create-modal-wide h3 { font-size: 15px !important; }
        .task-detail-modal-wide h4 { font-size: 13.5px !important; }
        .task-detail-modal-wide p, .task-detail-modal-wide label,
        .task-detail-modal-wide [style*="font-size:15px"], .task-detail-modal-wide [style*="font-size:14.5px"],
        .task-detail-modal-wide [style*="font-size:14px"], .task-detail-modal-wide [style*="font-size:13.5px"] { font-size: 12.5px !important; }
        .task-detail-modal-wide .form-input, .task-create-modal-wide .form-input { font-size: 12.5px !important; padding: 7px 10px !important; }
        .task-detail-modal-wide .btn-ghost, .task-detail-modal-wide .btn-primary, .task-detail-modal-wide .btn-secondary,
        .task-create-modal-wide .btn-ghost, .task-create-modal-wide .btn-primary { font-size: 12px !important; padding: 6px 12px !important; border-radius: 9px !important; }
        .task-detail-modal-wide .btn-ghost[title], .task-detail-modal-wide .btn-ghost[aria-label] { padding: 5px 9px !important; }
        .task-detail-modal-wide .badge { font-size: 10.5px !important; padding: 2px 8px !important; }
        .task-detail-modal-wide .task-checklist-checkbox.custom-checkbox { width: 18px; height: 18px; }
        .task-detail-modal-wide .task-checklist-checkbox .box i { font-size: 10px; }
        .task-detail-modal-wide .task-detail-body { font-size: 12.5px; }
        .task-detail-comments-inner { position: static !important; }
        .task-detail-comments-inner .task-list-scroll { overflow: visible !important; }
        .task-detail-comments {
            min-height: 0 !important;
            flex: 1 1 auto !important;
            border-left: none !important;
            border-top: 1px solid var(--border-soft);
            padding-left: 0 !important;
            padding-top: 16px;
        }
        /* Tombol aksi di card (Pindah ke Proses/Selesai, Setujui, dropdown Review) kegedean di
        HP relatif ke card-nya yang sempit - dikecilin khusus mobile, desktop tetap ukuran biasa. */
        .task-card .btn-sm { padding: 5px 10px !important; font-size: 11px !important; }
        .task-card-title { font-size: 12px; }
        .task-hero-column .task-card { padding: 9px 10px; }
        .task-card select.form-input { font-size: 11px !important; padding: 4px 6px !important; }
    }
</style>

<x-page-header title="Tugas" subtitle="" />

<div>
    {{-- Tab switcher inline dihapus (2026-09-19) - navigasi antar scope/board sekarang lewat
    halaman menu /tugas (tile) + dropdown nav global, bukan tab di dalam halaman ini lagi. --}}
    {{-- Konten --}}
    <div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px;">
            {{-- Judul scope (Todo Saya/Tugas Saya/dst) dihapus - tab yang lagi aktif sudah
            keliatan jelas dari pill terisi warna, keterangan teks di bawahnya jadi mubazir.
            Board tetap dapat judul karena nama project itu informasi baru, bukan pengulangan. --}}
            @if ($this->activeProject)
                @php $projectDesc = $this->activeProject->description; $longProjectDesc = filled($projectDesc) && (mb_strlen($projectDesc) > 800 || substr_count($projectDesc, "\n") >= 10); @endphp
                <div style="flex:1 1 100%;min-width:0;" x-data="{ full: false }">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;display:flex;align-items:center;gap:8px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $this->activeProject->color }};"></span>
                        {{ $this->activeProject->name }}
                    </h3>
                    @if (filled($projectDesc))
                        <p style="font-size:13px;line-height:1.5;color:var(--text-muted);margin:4px 0 0 18px;white-space:pre-line;word-break:break-word;"
                            @if ($longProjectDesc) :style="full ? {} : { display: '-webkit-box', '-webkit-line-clamp': '10', '-webkit-box-orient': 'vertical', overflow: 'hidden' }" @endif>{{ $projectDesc }}</p>
                        @if ($longProjectDesc)
                            <button type="button" @click="full = !full" style="background:none;border:none;padding:0;margin:4px 0 0 18px;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:700;color:var(--brand);">
                                <span x-text="full ? 'Sembunyikan detail' : 'Tampilkan detail'"></span>
                                <i class="fa-solid" :class="full ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:10px;"></i>
                            </button>
                        @endif
                    @endif
                    {{-- Ringkasan belum dilihat: lampiran, komentar, ceklis (dari semua kartu yang relevan buat Anda) dan diskusi. --}}
                    @if ($summary = $this->boardUnreadSummary)
                        @php $summaryLabels = ['attachment_added' => ['fa-paperclip', 'lampiran'], 'comment_added' => ['fa-comment', 'komentar'], 'checklist_done' => ['fa-square-check', 'ceklis selesai'], 'diskusi' => ['fa-comments', 'pesan diskusi']]; @endphp
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:8px 0 0 18px;font-size:12px;">
                            <span style="color:var(--text-muted);font-weight:700;">Belum dilihat:</span>
                            @foreach ($summary as $kind => $n)
                                <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:99px;background:rgba(220,38,38,0.1);color:#dc2626;font-weight:700;"><i class="fa-solid {{ $summaryLabels[$kind][0] }}" style="font-size:10px;"></i> {{ $n }} {{ $summaryLabels[$kind][1] }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;flex:1 1 100%;">
                {{-- Kiri: pencarian. Div tetap dirender kosong kalau nggak relevan, biar grup
                kanan tetap ke-push mentok kanan lewat space-between (bukan flex-end). --}}
                <div>
                    @if ($this->activeProject)
                        <div class="task-filter-tabs" role="tablist" aria-label="Filter tugas Board">
                            <button type="button" role="tab" wire:loading.attr="disabled" wire:target="setBoardFilter" wire:click="setBoardFilter('semua')" class="{{ $boardFilter === 'semua' ? 'is-active' : '' }}" aria-selected="{{ $boardFilter === 'semua' ? 'true' : 'false' }}">Semua</button>
                            <button type="button" role="tab" wire:loading.attr="disabled" wire:target="setBoardFilter" wire:click="setBoardFilter('saya')" class="{{ $boardFilter === 'saya' ? 'is-active' : '' }}" aria-selected="{{ $boardFilter === 'saya' ? 'true' : 'false' }}">Tugas Saya</button>
                            <button type="button" role="tab" wire:loading.attr="disabled" wire:target="setBoardFilter" wire:click="setBoardFilter('diskusi')" class="{{ $boardFilter === 'diskusi' ? 'is-active' : '' }}" aria-selected="{{ $boardFilter === 'diskusi' ? 'true' : 'false' }}"><i class="fa-regular fa-comments"></i> Diskusi @if (($n = $this->discussionUnread) > 0)<span title="{{ $n }} pesan baru" style="background:#dc2626;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;padding:0 4px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;margin-left:2px;">{{ $n > 99 ? '99+' : $n }}</span>@endif</button>
                        </div>
                    @endif
                    @if (! $activeProjectId && $scope === 'individu')
                        <div style="position:relative;width:240px;max-width:100%;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--text-faint);"></i>
                            <input type="text" wire:model.live.debounce.400ms="search" class="form-input" placeholder="Cari nama tugas / pegawai..." style="padding-left:30px;font-size:12.5px;width:100%;">
                        </div>
                    @endif
                </div>
                {{-- Kanan: counter refresh, aksi Board, tombol Tugas Baru. --}}
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
                    @if ((! $activeProjectId && in_array($scope, ['individu', 'saya'], true)) || ($this->activeProject && ! $this->isProjectArchived))
                        {{-- Refresh otomatis tiap 10 detik. Dilewati (hitung mundur tetap jalan) selagi tab
                        nggak kelihatan, kartu lagi di-drag, atau ada modal terbuka (biar ketikan/form nggak ke-reset). --}}
                        <div
                            x-data="{
                                secondsLeft: 10,
                                timer: null,
                                init() {
                                    this.timer = setInterval(() => {
                                        if (this.secondsLeft > 1) { this.secondsLeft--; return; }
                                        this.secondsLeft = 10;
                                        if (document.hidden || window.taskDragging || document.querySelector('.modal-overlay') || document.getElementById('board-discussion-input')?.value) return;
                                        $wire.$refresh();
                                    }, 1000);
                                },
                                destroy() { clearInterval(this.timer); },
                            }"
                            @board-filter-changed.window="secondsLeft = 10"
                            style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-faint);"
                            title="Board refresh otomatis tiap 10 detik"
                        >
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span x-text="secondsLeft + 'd'"></span>
                        </div>
                    @endif
                    @if ($this->activeProject)
                        <button type="button" wire:click="openMembersModal" class="btn-ghost btn-sm"><i class="fa-solid fa-users"></i> Anggota ({{ $this->projectMembers->count() }})</button>
                        @if ($this->canManageProjectMembers)
                            {{-- Board tanpa tugas dihapus, yang sudah punya tugas diarsipkan (lihat TaskService::removeProject). --}}
                            @php $projectHasTasks = $this->activeProject->tasks()->exists(); @endphp
                            <button type="button" wire:click="deleteProject" wire:confirm="{{ $projectHasTasks ? 'Arsipkan Board &quot;'.$this->activeProject->name.'&quot;? Board sudah punya tugas jadi tidak dihapus - Board pindah ke Arsip (halaman Tugas) dan hanya bisa dilihat.' : 'Hapus Board &quot;'.$this->activeProject->name.'&quot;? Board ini belum punya tugas sama sekali.' }}" class="btn-ghost btn-sm" style="color:var(--danger-text);">
                                <i class="fa-solid {{ $projectHasTasks ? 'fa-box-archive' : 'fa-trash' }}"></i> {{ $projectHasTasks ? 'Arsipkan Board' : 'Hapus Board' }}
                            </button>
                        @endif
                    @endif
                    <a href="{{ route('tugas') }}" wire:navigate class="btn-secondary btn-sm task-new-btn task-back-btn"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Tugas</a>
                    {{-- Di Board, tugas baru dibuat lewat tombol "+ Tambah tugas" di tiap List (bukan tombol global). --}}
                    @if (! $this->activeProject && ! in_array($scope, ['tim', 'saya'], true))
                        <button type="button" wire:click="openCreateTask" class="btn-primary btn-sm task-new-btn task-fab" aria-label="Tugas Baru"><i class="fa-solid fa-plus" style="font-size:12px;"></i> <span class="task-fab-label">Tugas Baru</span></button>
                    @endif
                </div>
            </div>
        </div>

        @if ($this->isProjectArchived)
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;margin-bottom:14px;padding:12px 14px;border-radius:12px;background:var(--surface-alt);border:1px solid var(--border);">
                <span style="font-size:13px;color:var(--text-body);">
                    <i class="fa-solid fa-box-archive"></i>
                    Board ini diarsipkan pada <strong>{{ $this->activeProject->archived_at->translatedFormat('j F Y, H:i') }}</strong> - hanya bisa dilihat.
                </span>
                @if ($this->isProjectOwner)
                    <button type="button" wire:click="restoreProject" wire:confirm="Pulihkan Board ini dari arsip?" class="btn-primary btn-sm"><i class="fa-solid fa-rotate-left"></i> Pulihkan</button>
                @endif
            </div>
        @endif

        @if ($this->activeProject && $boardFilter === 'diskusi')
            {{-- Tab Diskusi: ruang chat Board menggantikan papan. Tag "@Nama" + balas 1 tingkat, sama seperti komentar tugas. Ikut hitung mundur refresh di atas. --}}
            @php
                $dTaskService = app(\App\Services\Task\TaskService::class);
                $dUsers = $dTaskService->projectMentionableUsers($this->activeProject);
                $dNames = $dUsers->flatMap(fn ($u) => $dTaskService->mentionTerms($u))->all();
                $dPick = $dUsers->reject(fn ($u) => $u->id === auth()->id())
                    ->map(fn ($u) => ['label' => $dTaskService->mentionLabel($u), 'full' => $u->name])->values()->all();
                $dComments = $this->activeProject->comments()->get();
                $dReplies = $dComments->whereNotNull('parent_id')->groupBy('parent_id');
                $dReplyingTo = $discussionReplyToId ? $dComments->firstWhere('id', $discussionReplyToId) : null;
                $dIsNew = fn ($c) => $discussionSeenBefore && $c->user_id !== auth()->id() && $c->created_at->gt($discussionSeenBefore);
            @endphp
            <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:16px;padding:16px;height:calc(100vh - 280px);min-height:420px;display:flex;flex-direction:column;"
                x-data="{
                    names: @js($dPick), open: false, q: '',
                    matches() { const q = this.q.toLowerCase(); return this.names.filter(n => n.label.toLowerCase().includes(q) || n.full.toLowerCase().includes(q)).slice(0, 6) },
                    check(el) {
                        const m = el.value.slice(0, el.selectionStart).match(/(?:^|\s)@([^\s@]*)$/);
                        this.q = m ? m[1] : '';
                        this.open = !!m && this.matches().length > 0;
                    },
                    pick(el, name) {
                        const pos = el.selectionStart;
                        el.value = el.value.slice(0, pos).replace(/@([^\s@]*)$/, '@' + name + ' ') + el.value.slice(pos);
                        el.dispatchEvent(new Event('input'));
                        this.open = false;
                        el.focus();
                    }
                }"
                @discussion-sent.window="$nextTick(() => { const s = $refs.msgs; s.scrollTop = s.scrollHeight })">
                {{-- Pesan terlama di atas, terbaru di bawah (ala chat). --}}
                <div x-ref="msgs" class="task-list-scroll" x-init="$el.scrollTop = $el.scrollHeight"
                    style="flex:1;min-height:0;display:flex;flex-direction:column;gap:12px;overflow-y:auto;overscroll-behavior:contain;padding-right:6px;">
                    {{-- Ala chat: pesan sendiri di kanan, anggota lain di kiri. Balasan (1 tingkat) tampil setelah induknya dengan kutipan "Membalas ...". --}}
                    @forelse ($dComments->whereNull('parent_id')->flatMap(fn ($c) => collect([$c])->concat($dReplies->get($c->id, collect())))->values() as $comment)
                        @php
                            $mine = $comment->user_id === auth()->id();
                            $quoted = $comment->parent_id ? $dComments->firstWhere('id', $comment->parent_id) : null;
                        @endphp
                        <div style="display:flex;gap:8px;align-items:flex-end;justify-content:{{ $mine ? 'flex-end' : 'flex-start' }};">
                            @unless ($mine)
                                <x-avatar :user="$comment->user" :size="28" />
                            @endunless
                            <div style="max-width:78%;min-width:0;display:flex;flex-direction:column;align-items:{{ $mine ? 'flex-end' : 'flex-start' }};">
                                @unless ($mine)
                                    <span style="font-weight:700;font-size:12px;margin:0 0 2px 4px;">{{ $comment->user?->name }}</span>
                                @endunless
                                <div style="font-size:13.5px;padding:8px 12px;border-radius:{{ $mine ? '16px 16px 4px 16px' : '16px 16px 16px 4px' }};background:{{ $mine ? 'var(--brand-soft)' : 'var(--surface-alt)' }};{{ $dIsNew($comment) ? 'box-shadow:0 0 0 2px var(--brand);' : '' }}">
                                    @if ($quoted)
                                        <div style="font-size:11.5px;color:var(--text-muted);border-left:2px solid var(--border);padding-left:6px;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;"><i class="fa-solid fa-reply"></i> {{ $quoted->user?->name }}: {{ \Illuminate\Support\Str::limit($quoted->comment, 50) }}</div>
                                    @endif
                                    <p style="margin:0;white-space:pre-line;word-break:break-word;">{{ $comment->bodyHtml($dNames) }}</p>
                                </div>
                                <div style="font-size:11px;color:var(--text-muted);margin:2px 4px 0;">
                                    {{ $comment->created_at->diffForHumans() }}
                                    {{-- Balasan nggak bisa dibalas lagi (1 tingkat). --}}
                                    @if (! $comment->parent_id && ! $this->isProjectArchived)
                                        &middot; <button type="button" wire:click="startDiscussionReply('{{ $comment->id }}')" @click="$nextTick(() => document.getElementById('board-discussion-input')?.focus())" style="background:none;border:none;padding:0;cursor:pointer;font-family:inherit;font-size:11px;font-weight:700;color:var(--text-muted);">Balas</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p style="font-size:13.5px;color:var(--text-muted);text-align:center;margin:auto 0;">Belum ada diskusi.</p>
                    @endforelse
                </div>

                @unless ($this->isProjectArchived)
                    <div style="flex-shrink:0;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-soft);">
                        @if ($dReplyingTo)
                            <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                                <i class="fa-solid fa-reply"></i>
                                <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Membalas <strong>{{ $dReplyingTo->user?->name }}</strong>: {{ \Illuminate\Support\Str::limit($dReplyingTo->comment, 40) }}</span>
                                <button type="button" wire:click="cancelDiscussionReply" style="background:none;border:none;cursor:pointer;color:var(--text-muted);" aria-label="Batal membalas"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endif
                        <div style="display:flex;gap:8px;position:relative;align-items:flex-end;">
                            <textarea id="board-discussion-input" rows="1" class="form-input" style="font-size:13.5px;resize:none;overflow-y:auto;max-height:200px;line-height:1.4;" wire:model="newDiscussion" autocomplete="off"
                                x-init="$watch('$wire.newDiscussion', () => $nextTick(() => { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px' }))"
                                @input="check($el); $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'" @click="check($el)" @keyup.arrow-left="check($el)" @keyup.arrow-right="check($el)"
                                @keydown.escape="open = false"
                                @keydown.enter="if ($event.shiftKey) return; $event.preventDefault(); open ? pick($el, matches()[0]) : $wire.addDiscussion()"
                                placeholder="{{ $dReplyingTo ? 'Tulis balasan...' : 'Tulis pesan... (@ untuk menyebut)' }}"></textarea>
                            <button type="button" wire:click="addDiscussion" class="btn-primary" style="font-size:13.5px;padding:8px 14px;">Kirim</button>
                            <div x-show="open" x-cloak @click.outside="open = false" style="position:absolute;bottom:calc(100% + 4px);left:0;z-index:60;min-width:200px;max-height:220px;overflow-y:auto;background:var(--surface);border-radius:12px;padding:6px;box-shadow:0 8px 30px rgba(4,44,108,0.16),0 0 0 1px var(--border-soft);">
                                <template x-for="n in matches()" :key="n.full">
                                    <button type="button" @click="pick(document.getElementById('board-discussion-input'), n.label)" style="display:block;width:100%;padding:7px 10px;border:none;background:none;border-radius:8px;cursor:pointer;font-family:inherit;font-size:13px;color:var(--text-body);text-align:left;" onmouseover="this.style.background='var(--surface-alt)'" onmouseout="this.style.background='none'">
                                        <span x-text="'@' + n.label"></span>
                                        <span x-show="n.label !== n.full" x-text="n.full" style="color:var(--text-muted);font-size:11.5px;margin-left:4px;"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                @endunless
            </div>
        @elseif ($isTableScope)
            {{-- Desktop: tabel biasa. Mobile: list bertumpuk (kolom nggak muat, scroll-x tabel di
            HP nggak enak) - dua markup ditoggle CSS (.task-view-desktop/.task-view-mobile),
            bukan satu markup dipaksa responsive, karena bentuknya beda banget (grid vs stack). --}}
            <div class="task-view-desktop" style="overflow-x:auto;background:var(--surface);border-radius:12px;border:1px solid var(--border);">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;font-size:11px;color:var(--text-muted);border-bottom:1px solid var(--border);">
                            <th style="padding:10px 14px;">Kode</th>
                            <th style="padding:10px 14px;">Kebutuhan</th>
                            <th style="padding:10px 14px;">Status</th>
                            <th style="padding:10px 14px;">Prioritas</th>
                            @if ($scope === 'tim')
                                <th style="padding:10px 14px;">Pemberi Tugas</th>
                            @endif
                            <th style="padding:10px 14px;">Ditugaskan</th>
                            <th style="padding:10px 14px;">Tenggat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->tasks as $task)
                            <tr wire:click="openTaskDetail('{{ $task->id }}')" style="cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;">
                                <td style="padding:10px 14px;font-weight:600;">{{ $task->code }}</td>
                                <td style="padding:10px 14px;">{{ \Illuminate\Support\Str::limit($task->requirement, 60) }}</td>
                                <td style="padding:10px 14px;">
                                    <span class="badge {{ $badgeClass(TaskStatus::tryFrom($task->status)?->getColor() ?? 'gray') }}">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
                                </td>
                                <td style="padding:10px 14px;">{{ $task->priority?->getLabel() ?? $task->priority }}</td>
                                @if ($scope === 'tim')
                                    <td style="padding:10px 14px;">{{ $task->createdBy?->name ?? '-' }}</td>
                                @endif
                                <td style="padding:10px 14px;">{{ $task->assignedTo?->name ?? '-' }}</td>
                                <td style="padding:10px 14px;">
                                    {{ $fmtDate($task->due_date, 'j M Y, H:i') ?? '-' }}
                                    @if ($task->is_overdue) <span class="badge badge-rejected" style="margin-left:4px;">Terlambat</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $scope === 'tim' ? 7 : 6 }}" style="padding:20px;text-align:center;color:var(--text-muted);">Belum ada tugas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="task-view-mobile" style="background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden;">
                @forelse ($this->tasks as $task)
                    <div wire:click="openTaskDetail('{{ $task->id }}')" class="task-list-row">
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px;">
                                <span style="font-size:11px;font-weight:700;color:var(--text-faint);">{{ $task->code }}</span>
                                <span class="badge {{ $badgeClass(TaskStatus::tryFrom($task->status)?->getColor() ?? 'gray') }}">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
                            </div>
                            <p style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $task->requirement }}</p>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:11.5px;color:var(--text-muted);margin-top:5px;">
                                <span class="task-card-priority" style="--accent:{{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};font-size:11.5px;"><span class="dot"></span> {{ $task->priority?->getLabel() ?? $task->priority }}</span>
                                {{-- Individu: pemberi tugas sudah pasti user sendiri, nggak perlu
                                ditampilkan. Tim: tugas bisa datang dari siapa saja di tim, perlu
                                kelihatan biar nggak ketuker sama "Ditugaskan" (penerima tugas). --}}
                                @if ($scope === 'tim')
                                    <span>Dari <strong>{{ $task->createdBy?->name ?? '-' }}</strong></span>
                                @endif
                                <span>Ke <strong>{{ $task->assignedTo?->name ?? '-' }}</strong></span>
                                @if ($task->due_date)
                                    <span style="{{ $dueStyle($task) }}"><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $fmtDate($task->due_date, 'j M Y, H:i') }}</span>
                                @endif
                                @if ($task->is_overdue) <span class="badge badge-rejected">Terlambat</span> @endif
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;flex-shrink:0;"></i>
                    </div>
                @empty
                    <p style="padding:24px;text-align:center;color:var(--text-muted);margin:0;">Belum ada tugas.</p>
                @endforelse
            </div>
        @else
            @php $sectionCount = $this->activeProject ? $this->activeProject->lists->count() : count($boardStatuses); @endphp
            {{-- Zoom papan (0.5x-1.5x) pakai CSS zoom - beda dari transform, zoom ikut mempengaruhi
            layout jadi scroll & drop area tetap benar. Level disimpan per-browser (localStorage). --}}
            <div x-data="{
                z: 1,
                init() { try { const v = parseFloat(localStorage.getItem('taskBoardZoom')); if (v >= 0.5 && v <= 1.5) this.z = v; } catch (e) {} },
                set(v) { this.z = Math.min(1.5, Math.max(0.5, Math.round(v * 10) / 10)); try { localStorage.setItem('taskBoardZoom', this.z); } catch (e) {} },
            }">
                {{-- Mobile-only: melayang di samping FAB "Tugas Baru" (geser ke kanan kalau FAB nggak ada). --}}
                <div class="task-zoom-fab{{ $this->activeProject ? ' is-board' : '' }}">
                    <button type="button" @click="set(z - 0.1)" :disabled="z <= 0.5" title="Perkecil" aria-label="Perkecil"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                    <button type="button" @click="set(1)" title="Reset zoom" x-text="Math.round(z * 100) + '%'"></button>
                    <button type="button" @click="set(z + 0.1)" :disabled="z >= 1.5" title="Perbesar" aria-label="Perbesar"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                </div>
            <div class="task-board-grid{{ $activeProjectId || $scope === 'saya' ? ' task-board-scroll' : '' }}{{ $activeProjectId ? ' task-board-zoomable' : '' }}" :style="{ '--z': z }" style="background:var(--surface-page);border-radius:16px;padding:14px;border:1px solid var(--border-soft);--cols:{{ $sectionCount === 0 ? 1 : ($sectionCount % 2 === 0 ? 2 : 3) }};">
                @if ($this->activeProject)
                    @foreach ($this->activeProject->lists as $list)
                        {{-- Kolom List pakai gaya "hero" yang sama dengan kolom status Todo/Tugas Saya (gradient +
                        shape dekoratif + kartu putih). List berwarna custom -> gradient dari warnanya, tanpa
                        warna -> navy (green kalau List Selesai). overflow visible + shape dibungkus clip
                        supaya dropdown menu (⋮) nggak terpotong. --}}
                        @php
                            $presetKey = isset(\App\Models\Task\TaskList::COLOR_PRESETS[$list->color]) ? $list->color : null;
                            $listColor = ! $presetKey && $list->color && preg_match('/^#[0-9a-fA-F]{6}$/', $list->color) ? $list->color : null;
                            $listClass = $presetKey ?? ($listColor ? '' : ($list->is_final ? 'green' : 'navy'));
                        @endphp
                        <div class="task-hero-column task-list-column {{ $listClass }}" @if ($listColor) style="background:linear-gradient(130deg, {{ $listColor }} 0%, color-mix(in srgb, {{ $listColor }}, #ffffff 22%) 100%);" @endif>
                            <span class="task-hero-deco-clip"><span class="a-deco-1"></span><span class="a-deco-3"></span></span>
                            <div class="task-hero-column-header">
                                @if ($editingListId === $list->id)
                                    <input type="text" wire:model="editingListName" wire:keydown.enter="renameList" wire:keydown.escape="cancelEditList" class="form-input" style="font-size:12px;padding:3px 6px;height:auto;flex:1;" autofocus>
                                    <button type="button" wire:click="renameList" style="background:none;border:none;color:#fff;cursor:pointer;"><i class="fa-solid fa-check"></i></button>
                                    <button type="button" wire:click="cancelEditList" style="background:none;border:none;color:rgba(255,255,255,0.75);cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                                @else
                                    <span
                                        style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;{{ $this->canManageProjectMembers ? 'cursor:pointer;' : '' }}"
                                        @if ($this->canManageProjectMembers) wire:click="startEditList('{{ $list->id }}')" @endif
                                    >
                                        {{ $list->name }}
                                        @if ($list->is_final) <i class="fa-solid fa-flag-checkered" title="List Selesai"></i> @endif
                                        @if ($list->is_review) <i class="fa-solid fa-clipboard-check" title="List Review"></i> @endif
                                    </span>
                                    <span class="count-badge">{{ count($this->tasksByList[$list->id] ?? []) }}</span>
                                    @if ($this->canManageProjectMembers)
                                        <div class="dropdown">
                                            <button type="button" style="background:none;border:none;color:#fff;cursor:pointer;padding:2px 4px;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <button type="button" wire:click="moveListLeft('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-arrow-up"></i> Naik</button>
                                                <button type="button" wire:click="moveListRight('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-arrow-down"></i> Turun</button>
                                                <div class="dropdown-item" style="cursor:default;flex-direction:column;align-items:flex-start;gap:6px;">
                                                    <span><i class="fa-solid fa-palette"></i> Warna</span>
                                                    <div style="display:flex;flex-wrap:wrap;gap:6px;max-width:190px;">
                                                        @foreach (\App\Models\Task\TaskList::COLOR_PRESETS as $key => $label)
                                                            <button type="button" wire:click="changeListColor('{{ $list->id }}', '{{ $key }}')" class="task-swatch {{ $key }} {{ $listClass === $key ? 'is-selected' : '' }}" title="{{ $label }}" aria-label="{{ $label }}"></button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @unless ($list->is_final)
                                                    <button type="button" wire:click="markListFinal('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-flag-checkered"></i> Tandai Selesai</button>
                                                    <button type="button" wire:click="markListReview('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-clipboard-check"></i> {{ $list->is_review ? 'Lepas Tanda Review' : 'Tandai Review' }}</button>
                                                @endunless
                                                <button type="button" wire:click="deleteList('{{ $list->id }}')" wire:confirm="Hapus List ini? Pastikan sudah kosong." class="dropdown-item" style="color:var(--danger-text);"><i class="fa-solid fa-trash"></i> Hapus List</button>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                            {{-- Tombol tambah tugas per List, di ATAS kartu supaya nggak perlu scroll - nggak ada di List Review/Selesai (alurnya sendiri) dan Board arsip. --}}
                            @if (! $this->isProjectArchived && ! $list->is_review && ! $list->is_final)
                                <button type="button" wire:click="openCreateTask('{{ $list->id }}')" class="task-list-add-btn" style="margin-top:0;margin-bottom:10px;"><i class="fa-solid fa-plus"></i> Tambah</button>
                            @endif
                            <div class="task-hero-column-body" @if (! $this->isProjectArchived) wire:ignore.self data-list-id="{{ $list->id }}" data-list-final="{{ $list->is_final ? '1' : '0' }}" data-list-review="{{ $list->is_review ? '1' : '0' }}" @endif>
                                @forelse (($this->tasksByList[$list->id] ?? []) as $task)
                                    {{-- Yang boleh menggeser kartu: di List kerja cuma yang ditugaskan + pembuat Board (owner); di List Review cuma reviewer/pembuat tugas. Selain itu kartu terkunci (nggak bisa drag / pindah List). --}}
                                    @php $canMoveCard = ! $this->isProjectArchived && ($list->is_review ? (($task->reviewer_id ?? $task->created_by) === auth()->id()) : ($task->isAssignee(auth()->id()) || $this->isProjectOwner)); @endphp
                                    <div class="task-card{{ $canMoveCard ? '' : ' task-card-locked' }}" wire:click="openTaskDetail('{{ $task->id }}')" data-task-id="{{ $task->id }}" style="--accent:{{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};">
                                        <div class="task-card-top">
                                            @if ($task->recurrence && $canMoveCard && ! $list->is_final && ! $list->is_review)
                                                <label class="custom-checkbox" style="--accent:#16a34a;width:16px;height:16px;margin-top:2px;" title="Selesai &amp; ulangi ({{ \App\Models\Task\Task::RECURRENCES[$task->recurrence] }})" onclick="event.stopPropagation()">
                                                    <input type="checkbox" wire:click="completeRecurringTask('{{ $task->id }}')" wire:confirm="Tandai periode ini selesai? Tugas aktif lagi otomatis di periode berikutnya.">
                                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                                </label>
                                            @endif
                                            <p class="task-card-title">{{ \Illuminate\Support\Str::limit($task->requirement, 80) }}</p>
                                        </div>
                                        <div class="task-card-meta">
                                            <span style="display:flex;align-items:center;gap:10px;min-width:0;flex-wrap:wrap;">
                                                <span class="task-card-priority"><span class="dot"></span> {{ $task->priority?->getLabel() ?? $task->priority }}</span>
                                                {{-- Pelaksana: avatar saja (nama di tooltip), maks 3 lalu "+N". --}}
                                                @php
                                                    $cardAssignees = $task->assignees->map(fn ($a) => $a->user)->filter();
                                                    if ($cardAssignees->isEmpty() && $task->assignedTo) { $cardAssignees = collect([$task->assignedTo]); }
                                                    $moreAssignees = $cardAssignees->count() - 3;
                                                @endphp
                                                @if ($cardAssignees->isNotEmpty())
                                                    <span class="task-card-avatars" title="Pelaksana: {{ $cardAssignees->pluck('name')->join(', ') }}">
                                                        @foreach ($cardAssignees->take(3) as $cardAssignee)
                                                            <x-avatar :user="$cardAssignee" :size="20" />
                                                        @endforeach
                                                        @if ($moreAssignees > 0)
                                                            <span class="task-card-avatars-more">+{{ $moreAssignees }}</span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </span>
                                            @if ($task->needs_review) <span title="Wajib direview"><i class="fa-solid fa-clipboard-check"></i></span> @endif
                                            @if ($task->checklistItems->isNotEmpty())
                                                <span><i class="fa-solid fa-square-check"></i> {{ $task->checklistItems->where('is_done', true)->count() }}/{{ $task->checklistItems->count() }} @include("livewire.task.partials.unread-bubble", ["kind" => "checklist_done"])</span>
                                            @endif
                                            @if ($task->due_date)
                                                <span style="{{ $dueStyle($task) }}"><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $fmtDate($task->due_date, 'j M, H:i') }}</span>
                                            @endif
                                            @if ($task->recurrence) <span title="{{ \App\Models\Task\Task::RECURRENCES[$task->recurrence] }}"><i class="fa-solid fa-rotate"></i></span> @endif
                                            @if ($task->is_overdue) <span class="badge badge-rejected">Terlambat</span> @endif
                                        </div>
                                        @php $isDecider = ($task->reviewer_id ?? $task->created_by) === auth()->id(); @endphp
                                        @if ($this->isProjectArchived)
                                            {{-- Board diarsipkan: read-only, tanpa kontrol pindah/review. --}}
                                        @elseif ($list->is_review)
                                            {{-- List Review: cuma reviewer/pembuat yang bisa memutuskan. --}}
                                            @if ($isDecider)
                                                <div style="display:flex;gap:6px;margin-top:8px;">
                                                    <button type="button" wire:click="decideBoardReview('{{ $task->id }}', false)" onclick="event.stopPropagation()" class="btn-ghost btn-sm" style="flex:1;">Revisi</button>
                                                    <button type="button" wire:click="decideBoardReview('{{ $task->id }}', true)" wire:confirm="Setujui & selesaikan tugas ini?" onclick="event.stopPropagation()" class="btn-primary btn-sm" style="flex:1;">Setujui</button>
                                                </div>
                                            @else
                                                <p style="font-size:11px;color:var(--text-muted);margin:8px 0 0;font-style:italic;">Menunggu review.</p>
                                            @endif
                                        @endif
                                        <div class="task-card-footer">
                                            <span><i class="fa-solid fa-paperclip"></i> {{ $task->attachments_count }} @include("livewire.task.partials.unread-bubble", ["kind" => "attachment_added"])</span>
                                            <span><i class="fa-regular fa-comment"></i> {{ $task->comments->count() }} @include("livewire.task.partials.unread-bubble", ["kind" => "comment_added"])</span>
                                            @if ($task->rating)
                                                <span><i class="fa-solid fa-star" style="color:#f5a524;"></i> {{ $task->rating }}</span>
                                            @endif
                                            {{-- Pindah List: tombol ikon ringkas (sejajar lampiran/komentar), menu daftar List tujuan. --}}
                                            @if ($canMoveCard && ! $list->is_review && $this->activeProject->lists->count() > 1)
                                                <div x-data="{ open: false }" @click.stop @click.outside="open = false" class="task-card-move">
                                                    <button type="button" @click="open = ! open" title="Pindah ke List lain" aria-label="Pindah ke List lain"><i class="fa-solid fa-right-left"></i></button>
                                                    <div x-show="open" x-cloak class="task-card-move-menu">
                                                        @foreach ($this->activeProject->lists as $target)
                                                            @continue($target->id === $list->id)
                                                            {{-- Tugas wajib review nggak boleh langsung ke List Selesai (harus lewat List Review). --}}
                                                            @continue($target->is_final && $task->needs_review && $this->activeProject->lists->contains('is_review', true))
                                                            <button type="button" @click="open = false" wire:click="moveTaskToList('{{ $task->id }}', '{{ $target->id }}')" @if ($target->is_review) wire:confirm="Ajukan tugas ini untuk direview?" @endif>{{ $target->name }}</button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:12px 0;margin:0;">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                    @if ($this->canManageProjectMembers)
                        <div style="background:var(--surface);border-radius:12px;padding:10px;border:1px solid var(--border-soft);">
                            <div style="display:flex;gap:6px;">
                                <input type="text" class="form-input" style="font-size:12.5px;" wire:model="newListName" wire:keydown.enter="createList" placeholder="Nama List baru...">
                                <button type="button" wire:click="createList" class="btn-ghost btn-sm"><i class="fa-solid fa-plus"></i></button>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                                @foreach (\App\Models\Task\TaskList::COLOR_PRESETS as $key => $label)
                                    <button type="button" wire:click="$set('newListColor', '{{ $key }}')" class="task-swatch {{ $key }} {{ $newListColor === $key ? 'is-selected' : '' }}" title="{{ $label }}" aria-label="{{ $label }}"></button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    @foreach ($boardStatuses as $status)
                        @php
                            $statusAccent = match ($status->getColor()) {
                                'warning' => '#a16207',
                                'info' => 'var(--brand)',
                                'success' => 'var(--brand-success)',
                                'danger' => '#b91c1c',
                                default => 'var(--text-muted)',
                            };
                        @endphp
                        @if ($useBoardHero)
                            <div class="task-hero-column {{ $heroClassFor($status) }}">
                                <span class="a-deco-1"></span>
                                <span class="a-deco-3"></span>
                                <div class="task-hero-column-header">
                                    <span>{{ $status->getLabel() }}</span>
                                    <span class="count-badge">{{ count($this->tasksByStatus[$status->value] ?? []) }}</span>
                                </div>
                                <div class="task-hero-column-body" @if ($useBoardHero) wire:ignore.self data-status-list="{{ $status->value }}" @endif>
                        @else
                        <div style="background:var(--surface);border-radius:12px;overflow:hidden;border:1px solid var(--border-soft);">
                            <div style="background:var(--surface-alt);border-left:4px solid {{ $statusAccent }};padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                                <span style="color:var(--text-heading);font-weight:700;font-size:13.5px;">{{ $status->getLabel() }}</span>
                                <span style="background:var(--border-soft);color:var(--text-muted);font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByStatus[$status->value] ?? []) }}</span>
                            </div>
                            <div style="padding:10px;display:flex;flex-direction:column;gap:8px;">
                        @endif
                                @forelse (($this->tasksByStatus[$status->value] ?? []) as $task)
                                    <div class="task-card" wire:click="openTaskDetail('{{ $task->id }}')" data-task-id="{{ $task->id }}" style="--accent:{{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};">
                                        <div class="task-card-top">
                                            <p class="task-card-title">{{ \Illuminate\Support\Str::limit($task->requirement, 80) }}</p>
                                            @if ($task->assignedTo && ! $task->is_self_assigned)
                                                <span class="task-card-avatar"><x-avatar :user="$task->assignedTo" :size="22" /></span>
                                            @endif
                                        </div>
                                        <div class="task-card-meta">
                                            <span class="task-card-priority"><span class="dot"></span> {{ $task->priority?->getLabel() ?? $task->priority }}</span>
                                            @if ($task->checklistItems->isNotEmpty())
                                                <span><i class="fa-solid fa-square-check"></i> {{ $task->checklistItems->where('is_done', true)->count() }}/{{ $task->checklistItems->count() }} @include("livewire.task.partials.unread-bubble", ["kind" => "checklist_done"])</span>
                                            @endif
                                            @if ($task->due_date)
                                                <span style="{{ $dueStyle($task) }}"><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $fmtDate($task->due_date, 'j M, H:i') }}</span>
                                            @endif
                                            @if ($task->is_overdue) <span class="badge badge-rejected">Terlambat</span> @endif
                                        </div>
                                        @if ($task->assignedTo && ! $task->is_self_assigned)
                                            <div class="task-card-assignee">{{ $task->assignedTo->profile?->nickname ?: $task->assignedTo->name }}</div>
                                        @endif
                                        @if ($task->status ===\App\Enum\Task\TaskStatus::REVIEW->value)
                                            {{-- Di REVIEW cuma creator yang bisa mutusin: kembalikan buat direvisi, atau selesaikan. --}}
                                            @if ($task->created_by === auth()->id())
                                                <select wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" onclick="event.stopPropagation()" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;margin-top:8px;">
                                                    <option value="" selected disabled>Ubah ke...</option>
                                                    <option value="{{ \App\Enum\Task\TaskStatus::IN_PROGRESS->value }}">Sedang Dalam Proses</option>
                                                    <option value="{{ \App\Enum\Task\TaskStatus::COMPLETE->value }}">Selesai</option>
                                                </select>
                                            @else
                                                <p style="font-size:11px;color:var(--text-muted);margin:8px 0 0;font-style:italic;">Menunggu penilaian.</p>
                                            @endif
                                        @elseif ($task->status !== \App\Enum\Task\TaskStatus::COMPLETE->value && ($task->status !== \App\Enum\Task\TaskStatus::TODO->value || $task->assigned_to === auth()->id()))
                                            @php
                                                $order = ($task->is_self_assigned || ! $task->needs_review) ? \App\Enum\Task\TaskStatus::BOARD_ORDER_SELF_ASSIGNED : \App\Enum\Task\TaskStatus::BOARD_ORDER;
                                                $nextStatus = $order[array_search(\App\Enum\Task\TaskStatus::from($task->status), $order, true) + 1] ?? null;
                                            @endphp
                                            @if ($nextStatus)
                                                @php
                                                    $nextShortLabel = match ($nextStatus) {
                                                        \App\Enum\Task\TaskStatus::IN_PROGRESS => 'Mulai',
                                                        \App\Enum\Task\TaskStatus::REVIEW => 'Ajukan Review',
                                                        \App\Enum\Task\TaskStatus::COMPLETE => 'Selesai',
                                                        default => $nextStatus->getLabel(),
                                                    };
                                                @endphp
                                                <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ $nextStatus->value }}')" onclick="event.stopPropagation()" class="btn-ghost btn-sm" style="margin-top:8px;width:100%;" title="Pindah ke {{ $nextStatus->getLabel() }}" @if ($nextStatus === \App\Enum\Task\TaskStatus::COMPLETE) wire:confirm="Apakah tugas ini sudah selesai?" @endif>
                                                    {{ $nextShortLabel }} <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i>
                                                </button>
                                            @endif
                                        @endif
                                        @if ($task->created_by === auth()->id() && ! $task->isAssignee(auth()->id()) && ! $task->is_self_assigned && in_array($task->status, [\App\Enum\Task\TaskStatus::TODO->value, \App\Enum\Task\TaskStatus::IN_PROGRESS->value], true))
                                            <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ \App\Enum\Task\TaskStatus::COMPLETE->value }}')" onclick="event.stopPropagation()" wire:confirm="Selesaikan tugas ini tanpa menunggu pelaksana?" class="btn-ghost btn-sm" style="margin-top:8px;width:100%;">Selesaikan</button>
                                        @endif

                                        @unless ($task->is_self_assigned)
                                            <div class="task-card-footer">
                                                <span><i class="fa-solid fa-paperclip"></i> {{ $task->attachments_count }} @include("livewire.task.partials.unread-bubble", ["kind" => "attachment_added"])</span>
                                                <span><i class="fa-regular fa-comment"></i> {{ $task->comments->count() }} @include("livewire.task.partials.unread-bubble", ["kind" => "comment_added"])</span>
                                                @if ($task->rating)
                                                    <span><i class="fa-solid fa-star" style="color:#f5a524;"></i> {{ $task->rating }}</span>
                                                @endif
                                            </div>
                                        @endunless
                                    </div>
                                @empty
                                    <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:12px 0;margin:0;">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            </div>
        @endif

        @if ($this->historyTasks)
            <div x-data="{ open: false }" style="margin-top:24px;">
                <button type="button" @click="open = ! open" class="btn-ghost btn-sm">
                    <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
                    <i class="fa-solid fa-chevron-down" :style="open && 'transform:rotate(180deg);'" style="font-size:10px;transition:transform 0.15s;"></i>
                </button>
                <div x-show="open" x-cloak style="margin-top:12px;">
                    <div class="task-list-scroll" style="overflow-x:auto;background:var(--surface);border-radius:12px;border:1px solid var(--border);">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="text-align:left;font-size:11px;color:var(--text-muted);border-bottom:1px solid var(--border);">
                                    <th style="padding:10px 14px;">Kode</th>
                                    <th style="padding:10px 14px;">Kebutuhan</th>
                                    <th style="padding:10px 14px;">Prioritas</th>
                                    <th style="padding:10px 14px;">Ditugaskan</th>
                                    <th style="padding:10px 14px;">Selesai</th>
                                    <th style="padding:10px 14px;">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->historyTasks as $task)
                                    <tr wire:click="openTaskDetail('{{ $task->id }}')" style="cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;">
                                        <td style="padding:10px 14px;font-weight:600;">{{ $task->code }}</td>
                                        <td style="padding:10px 14px;">{{ \Illuminate\Support\Str::limit($task->requirement, 60) }}</td>
                                        <td style="padding:10px 14px;">{{ $task->priority?->getLabel() ?? $task->priority }}</td>
                                        <td style="padding:10px 14px;">{{ $task->assignedTo?->name ?? '-' }}</td>
                                        <td style="padding:10px 14px;">{{ $fmtDate($task->completed_date, 'j M Y') ?? '-' }}</td>
                                        <td style="padding:10px 14px;">
                                            @if ($task->rating)
                                                <i class="fa-solid fa-star" style="color:#f5a524;"></i> {{ $task->rating }}
                                            @else
                                                <span style="color:var(--text-faint);">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-muted);">Belum ada riwayat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:12px;">{{ $this->historyTasks->links() }}</div>
                </div>
            </div>
        @endif
    </div>
</div>

@if ($showTaskModal)
    <div class="modal-overlay" wire:click="closeTaskModal">
        <div class="modal-box task-create-modal-wide" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                Tugas Baru @if ($this->activeProject) <span style="font-weight:400;color:var(--text-muted);font-size:13px;">di {{ $this->activeProject->name }}</span> @endif
            </h3>
            <form wire:submit="createTask" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Kebutuhan *</label>
                    <input type="text" class="form-input" wire:model="requirement" maxlength="255" placeholder="Judul singkat tugas, mis. Susun laporan bulanan">
                    @error('requirement') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" rows="3" wire:model="description" placeholder="Uraian detail, konteks, atau acuan pengerjaan (opsional)"></textarea>
                    @error('description') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div class="date-grid">
                    <div>
                        <label class="form-label">Tenggat</label>
                        <div style="display:flex;gap:6px;">
                            <div style="display:flex;flex:1;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;">
                                <input type="date" class="form-input" wire:model="due_date" style="border:0;border-radius:0;box-shadow:none;flex:1;">
                                <input type="time" class="form-input" wire:model="due_time" placeholder="23:59" style="border:0;border-left:1px solid var(--border-color,#d1d5db);border-radius:0;box-shadow:none;max-width:110px;">
                            </div>
                            @if ($this->activeProject)
                                <label title="Ulangi: {{ $recurrence ? \App\Models\Task\Task::RECURRENCES[$recurrence] : 'Tidak berulang' }}" style="position:relative;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:40px;padding:0 10px;flex-shrink:0;white-space:nowrap;font-size:13px;border:1px solid {{ $recurrence ? 'var(--primary,#2563eb)' : 'var(--border-color,#d1d5db)' }};border-radius:8px;cursor:pointer;color:{{ $recurrence ? 'var(--primary,#2563eb)' : 'var(--text-muted)' }};">
                                    <i class="fa-solid fa-rotate"></i>@if ($recurrence)<span>{{ \App\Models\Task\Task::RECURRENCES[$recurrence] }}</span>@endif
                                    <select wire:model.live="recurrence" style="position:absolute;inset:0;width:100%;opacity:0;cursor:pointer;">
                                        <option value="">Tidak berulang</option>
                                        @foreach (\App\Models\Task\Task::RECURRENCES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Jam kosong = 23:59.</p>
                        @error('due_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">Tenggat wajib diisi untuk tugas berulang.</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Prioritas</label>
                        <select class="form-input" wire:model="priority">
                            @foreach (TaskPriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($this->activeProject && ! $this->canAssignInProject)
                    {{-- Anggota biasa (bukan owner/Assigner): tugas di Board otomatis untuk dirinya sendiri. --}}
                    <p style="font-size:12.5px;color:var(--text-muted);margin:0;padding:10px 12px;background:var(--surface-alt);border-radius:10px;">
                        <i class="fa-solid fa-circle-info"></i> Tugas ini untuk kamu sendiri. Minta owner Board menandai kamu sebagai <strong>Assigner</strong> kalau perlu menugaskan ke anggota lain.
                    </p>

                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="custom-checkbox" style="--accent:#042c6c;">
                            <input type="checkbox" wire:model="notifyOnComplete">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;">Kasih tahu aku kalau kartu ini masuk List "Selesai"</span>
                    </div>
                @elseif ($this->activeProject)
                    <div>
                        <label class="form-label">Tugaskan ke</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="Saya sendiri" wire:model="assigned_to">
                                <option value="">Saya sendiri</option>
                                @foreach ($this->projectMembers as $member)
                                    @continue($member->user_id === auth()->id())
                                    <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Reviewer (yang berhak kasih rating)</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="Saya sendiri (pembuat tugas)" wire:model="reviewerId">
                                <option value="">Saya sendiri (pembuat tugas)</option>
                                @foreach ($this->projectMembers as $member)
                                    @continue($member->user_id === auth()->id())
                                    <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kalau diisi, cuma orang ini yang bisa kasih rating begitu tugas selesai - bukan cuma kamu sebagai pembuat.</p>
                    </div>

                    {{-- Wajib review: tugas nggak bisa langsung ke List Selesai, harus lewat List yang ditandai
                    "Review" dulu dan dinilai reviewer. Butuh Board punya List Review. --}}
                    @php $boardHasReviewList = $this->activeProject->lists->contains('is_review', true); @endphp
                    <div style="display:flex;align-items:flex-start;gap:10px;{{ $boardHasReviewList ? '' : 'opacity:0.6;' }}">
                        <label class="custom-checkbox" style="--accent:#042c6c;margin-top:2px;">
                            <input type="checkbox" wire:model="needsReview" @disabled(! $boardHasReviewList)>
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <div>
                            <span style="font-size:13px;font-weight:600;">Wajib direview</span>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">
                                @if ($boardHasReviewList)
                                    Tugas harus masuk List Review dan dinilai dulu sebelum bisa Selesai.
                                @else
                                    Board ini belum punya List Review - tandai salah satu List sebagai Review lewat menu ⋮ untuk mengaktifkan opsi ini.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="custom-checkbox" style="--accent:#042c6c;">
                            <input type="checkbox" wire:model="notifyOnComplete">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;">Kasih tahu aku kalau kartu ini masuk List "Selesai"</span>
                    </div>
                @elseif ($this->hasSubordinates && $scope !== 'todo')
                    <div>
                        <label class="form-label">Tugaskan ke *</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="-- Pilih Pegawai --" wire:model="assigned_to">
                                <option value="">-- Pilih Pegawai --</option>
                                @foreach ($this->subordinates as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('assigned_to') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="custom-checkbox" style="--accent:#042c6c;">
                            <input type="checkbox" wire:model.live="needsReview">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;">Butuh Approval Saya <span style="color:var(--text-muted);">— minta ditinjau dulu sebelum beneran selesai</span></span>
                    </div>

                    @if ($needsReview)
                        <div style="display:flex;align-items:center;gap:10px;padding-left:29px;">
                            <label class="custom-checkbox" style="--accent:#042c6c;">
                                <input type="checkbox" wire:model="notifyOnReview">
                                <span class="box"><i class="fa-solid fa-check"></i></span>
                            </label>
                            <span style="font-size:12.5px;color:var(--text-muted);">Kasih tahu aku kalau pelaksana mengajukan review</span>
                        </div>
                    @else
                        <div style="display:flex;align-items:center;gap:10px;padding-left:29px;">
                            <label class="custom-checkbox" style="--accent:#042c6c;">
                                <input type="checkbox" wire:model="notifyOnComplete">
                                <span class="box"><i class="fa-solid fa-check"></i></span>
                            </label>
                            <span style="font-size:12.5px;color:var(--text-muted);">Kasih tahu aku kalau pelaksana sudah selesai menjalankan tugas</span>
                        </div>
                    @endif
                @endif

                <div>
                    <label class="form-label">Checklist</label>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:6px;">
                        @foreach ($checklist as $i => $item)
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="flex:1;font-size:12px;">{{ $item }}</span>
                                <button type="button" wire:click="removeChecklistDraft({{ $i }})" style="color:var(--danger-text);background:none;border:none;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <div style="display:flex;gap:6px;">
                        <input type="text" class="form-input" wire:model="newChecklistTitle" wire:keydown.enter.prevent="addChecklistDraft" placeholder="Tambah item...">
                        <button type="button" wire:click="addChecklistDraft" class="btn-ghost">Tambah</button>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Buat Tugas</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($showEditTaskModal)
    <div class="modal-overlay" wire:click="closeEditTaskModal">
        <div class="modal-box task-create-modal-wide" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Edit Tugas</h3>
            <form wire:submit="updateTaskDetails" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Kebutuhan *</label>
                    <input type="text" class="form-input" wire:model="editRequirement" maxlength="255" placeholder="Judul singkat tugas, mis. Susun laporan bulanan">
                    @error('editRequirement') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" rows="3" wire:model="editDescription" placeholder="Uraian detail, konteks, atau acuan pengerjaan (opsional)"></textarea>
                    @error('editDescription') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div class="date-grid">
                    <div>
                        <label class="form-label">Tenggat</label>
                        <div style="display:flex;gap:6px;">
                            <div style="display:flex;flex:1;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;">
                                <input type="date" class="form-input" wire:model="editDueDate" style="border:0;border-radius:0;box-shadow:none;flex:1;">
                                <input type="time" class="form-input" wire:model="editDueTime" placeholder="23:59" style="border:0;border-left:1px solid var(--border-color,#d1d5db);border-radius:0;box-shadow:none;max-width:110px;">
                            </div>
                            @if ($this->activeProject)
                                <label title="Ulangi: {{ $editRecurrence ? \App\Models\Task\Task::RECURRENCES[$editRecurrence] : 'Tidak berulang' }}" style="position:relative;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:40px;padding:0 10px;flex-shrink:0;white-space:nowrap;font-size:13px;border:1px solid {{ $editRecurrence ? 'var(--primary,#2563eb)' : 'var(--border-color,#d1d5db)' }};border-radius:8px;cursor:pointer;color:{{ $editRecurrence ? 'var(--primary,#2563eb)' : 'var(--text-muted)' }};">
                                    <i class="fa-solid fa-rotate"></i>@if ($editRecurrence)<span>{{ \App\Models\Task\Task::RECURRENCES[$editRecurrence] }}</span>@endif
                                    <select wire:model.live="editRecurrence" style="position:absolute;inset:0;width:100%;opacity:0;cursor:pointer;">
                                        <option value="">Tidak berulang</option>
                                        @foreach (\App\Models\Task\Task::RECURRENCES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Jam kosong = 23:59.</p>
                        @error('editDueDate') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">Tenggat wajib diisi untuk tugas berulang.</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Prioritas</label>
                        <select class="form-input" wire:model="editPriority">
                            @foreach (TaskPriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeEditTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($this->selectedTask)
    @php
        $task = $this->selectedTask;
        $canEdit = $task->status !== \App\Enum\Task\TaskStatus::COMPLETE->value
            && ($task->is_self_assigned ? $task->assigned_to === auth()->id() : $task->created_by === auth()->id());
        $canDelete = $task->is_self_assigned
            ? $task->assigned_to === auth()->id()
            : ($task->created_by === auth()->id() && in_array($task->status, [\App\Enum\Task\TaskStatus::TODO->value, \App\Enum\Task\TaskStatus::IN_PROGRESS->value], true));
        $canRevertComplete = ! $task->is_self_assigned && ! $task->needs_review
            && $task->status === \App\Enum\Task\TaskStatus::COMPLETE->value
            && $task->created_by === auth()->id()
            && $task->completed_date
            && \Carbon\Carbon::parse($task->completed_date)->gt(now()->subDay());
        // Penilai (reviewer/pembuat) WAJIB kasih rating begitu tugas Selesai - modal nggak bisa
        // ditutup sebelum dinilai. Tugas Todo Saya (catatan pribadi) nggak ada penilaian.
        // Catatan pribadi (Todo Saya) = self-assigned di luar Board: nggak ada komentar/lampiran. Tugas Board
        // yang ditugaskan ke diri sendiri tetap punya kolom komentar & lampiran (kolaborasi antar anggota).
        $isPersonalNote = $task->is_self_assigned && ! $task->project_id;
        $ratingRequired = $task->requiresRatingFrom(auth()->id());
    @endphp
    <div class="modal-overlay" @if (! $ratingRequired) @click="$wire.closeTaskDetail()" @endif>
        <div class="modal-box task-detail-modal-wide" style="border-top:4px solid {{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};" onclick="event.stopPropagation()">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                <div style="min-width:0;">
                    <p style="font-size:11px;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;color:var(--text-faint);margin:0 0 4px;">{{ $task->code }}</p>
                    <div style="display:flex;align-items:center;gap:10px;">
                        @if ($task->recurrence && $task->project_id && $task->status !== \App\Enum\Task\TaskStatus::COMPLETE->value && ! $this->isProjectArchived && ($task->isAssignee(auth()->id()) || $this->isProjectOwner))
                            <label class="custom-checkbox" style="--accent:#16a34a;width:24px;height:24px;" title="Selesai &amp; ulangi ({{ \App\Models\Task\Task::RECURRENCES[$task->recurrence] }})">
                                <input type="checkbox" wire:click="completeRecurringTask('{{ $task->id }}')" wire:confirm="Tandai periode ini selesai? Tugas aktif lagi otomatis di periode berikutnya.">
                                <span class="box"><i class="fa-solid fa-check" style="font-size:13px;"></i></span>
                            </label>
                        @endif
                        <h3 style="font-size:19px;font-weight:700;color:var(--text-heading);margin:0;line-height:1.35;">{{ $task->requirement }}</h3>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;flex-shrink:0;">
                    @if ($canRevertComplete)
                        <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ \App\Enum\Task\TaskStatus::IN_PROGRESS->value }}')" class="btn-ghost btn-sm" title="Kembalikan ke Sedang Dalam Proses (masih < 24 jam)"><i class="fa-solid fa-rotate-left"></i> Kembalikan</button>
                    @endif
                    @if ($canEdit)
                        <button type="button" wire:click="openEditTask('{{ $task->id }}')" class="btn-ghost" style="padding:6px 10px;" title="Edit"><i class="fa-solid fa-pen"></i></button>
                    @endif
                    @if ($canDelete)
                        <button type="button" wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Hapus tugas ini? Ngga bisa dibalikin lagi." class="btn-ghost" style="padding:6px 10px;color:var(--danger-text);" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                    @endif
                    @unless ($ratingRequired)
                        <button type="button" @click="$wire.closeTaskDetail()" class="btn-ghost" style="padding:6px 10px;" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
                    @endunless
                </div>
            </div>

            <div class="task-detail-body" style="{{ $isPersonalNote ? '' : 'display:flex;gap:24px;' }}">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;font-size:13px;">
                        <span class="badge {{ $badgeClass(TaskStatus::tryFrom($task->status)?->getColor() ?? 'gray') }}">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
                        <span class="task-card-priority" style="--accent:{{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};font-size:13px;"><span class="dot"></span> {{ $task->priority?->getLabel() ?? $task->priority }}</span>
                        @if ($canEdit)
                            <input type="datetime-local" class="form-input" style="width:auto;padding:3px 8px;font-size:12px;{{ $dueStyle($task) }}" title="Tenggat" value="{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : '' }}" wire:change="setTaskDueDate('{{ $task->id }}', $event.target.value)">
                        @elseif ($task->due_date)
                            <span style="color:var(--text-muted);display:inline-flex;align-items:center;gap:5px;{{ $dueStyle($task) }}"><i class="fa-regular fa-calendar" style="font-size:11px;"></i> {{ $fmtDate($task->due_date, 'j M Y, H:i') }}</span>
                        @endif
                        @if ($canEdit && $task->project_id && $task->due_date)
                            <label title="Ulangi: {{ $task->recurrence ? \App\Models\Task\Task::RECURRENCES[$task->recurrence] : 'Tidak berulang' }}" style="position:relative;display:inline-flex;align-items:center;justify-content:center;gap:5px;min-width:28px;height:28px;padding:0 8px;border:1px solid {{ $task->recurrence ? 'var(--primary,#2563eb)' : 'var(--border-color,#d1d5db)' }};border-radius:8px;cursor:pointer;font-size:12px;color:{{ $task->recurrence ? 'var(--primary,#2563eb)' : 'var(--text-muted)' }};">
                                <i class="fa-solid fa-rotate"></i>@if ($task->recurrence)<span>{{ \App\Models\Task\Task::RECURRENCES[$task->recurrence] }}</span>@endif
                                <select style="position:absolute;inset:0;width:100%;opacity:0;cursor:pointer;" wire:change="setTaskRecurrence('{{ $task->id }}', $event.target.value)">
                                    <option value="">Tidak berulang</option>
                                    @foreach (\App\Models\Task\Task::RECURRENCES as $key => $label)
                                        <option value="{{ $key }}" @selected($task->recurrence === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @elseif ($task->recurrence)
                            <span class="badge badge-cancelled"><i class="fa-solid fa-rotate"></i> {{ \App\Models\Task\Task::RECURRENCES[$task->recurrence] }}</span>
                        @endif
                        @if ($task->is_overdue)<span class="badge badge-rejected">Terlambat</span>@endif
                        @if ($task->recurrence && $task->recurrence_resume_at)<span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Periode ini selesai &middot; aktif lagi {{ $task->recurrence_resume_at->translatedFormat('j M Y') }}</span>@endif
                        @if ($task->project)<span class="badge badge-cancelled"><i class="fa-solid fa-diagram-project"></i> {{ $task->project->name }}</span>@endif
                    </div>

                    {{-- Deskripsi (uraian detail) dipisah dari Kebutuhan (judul di atas). --}}
                    @if (filled($task->description))
                        {{-- Deskripsi panjang diringkas 4 baris, tombol buat buka/tutup. --}}
                        @php $longDescription = mb_strlen($task->description) > 240 || substr_count($task->description, "\n") >= 4; @endphp
                        <div style="margin-bottom:18px;" x-data="{ full: false }">
                            <h4 style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.03em;margin:0 0 6px;">Deskripsi</h4>
                            <p style="font-size:13.5px;line-height:1.5;color:var(--text-body);margin:0;white-space:pre-line;word-break:break-word;"
                                @if ($longDescription) :style="full ? {} : { display: '-webkit-box', '-webkit-line-clamp': '4', '-webkit-box-orient': 'vertical', overflow: 'hidden' }" @endif>{{ $task->description }}</p>
                            @if ($longDescription)
                                <button type="button" @click="full = !full" style="background:none;border:none;padding:0;margin-top:4px;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:700;color:var(--brand);">
                                    <span x-text="full ? 'Sembunyikan detail' : 'Tampilkan detail'"></span>
                                    <i class="fa-solid" :class="full ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size:10px;"></i>
                                </button>
                            @endif
                        </div>
                    @endif

                    @unless ($isPersonalNote)
                        {{-- Pembuat, pelaksana & reviewer: avatar saja, nama muncul saat di-hover (title avatar). --}}
                        <div style="margin-bottom:18px;">
                            <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;padding:10px 12px;background:var(--surface-alt);border-radius:10px;font-size:12.5px;">
                                <div style="display:flex;align-items:center;gap:7px;">
                                    <span style="color:var(--text-muted);">Pembuat</span>
                                    <x-avatar :user="$task->createdBy" :size="24" />
                                </div>
                                <div style="display:flex;align-items:center;gap:7px;">
                                    <span style="color:var(--text-muted);">Pelaksana</span>
                                    <span style="display:inline-flex;align-items:center;gap:4px;">
                                        @forelse ($task->assignees as $assignee)
                                            <x-avatar :user="$assignee->user" :size="24" />
                                        @empty
                                            @if ($task->assignedTo)
                                                <x-avatar :user="$task->assignedTo" :size="24" />
                                            @endif
                                        @endforelse
                                    </span>
                                    {{-- Tambah pelaksana: owner/Assigner Board, selama kartu belum Selesai. --}}
                                    @if ($task->project_id && $this->canAssignInProject && ! $this->isProjectArchived
                                        && $task->status !== \App\Enum\Task\TaskStatus::COMPLETE->value)
                                        {{-- Klik "Tambah" -> daftar anggota Board, klik nama = langsung ditambahkan. --}}
                                        <div x-data="{ open: false }" @click.outside="open = false" style="position:relative;">
                                            <button type="button" @click="open = ! open" class="btn-ghost btn-sm" style="padding:3px 10px;"><i class="fa-solid fa-plus" style="font-size:10px;"></i> Tambah</button>
                                            <div x-show="open" x-cloak style="position:absolute;top:calc(100% + 6px);left:0;z-index:50;min-width:200px;max-height:240px;overflow-y:auto;background:var(--surface);border-radius:12px;padding:6px;box-shadow:0 8px 30px rgba(4,44,108,0.16),0 0 0 1px var(--border-soft);">
                                                @forelse ($this->projectMembers->reject(fn ($m) => $task->isAssignee($m->user_id)) as $member)
                                                    <button type="button" wire:click="addAssignee('{{ $task->id }}', '{{ $member->user_id }}')" @click="open = false" style="display:flex;align-items:center;gap:8px;width:100%;padding:7px 10px;border:none;background:none;border-radius:8px;cursor:pointer;font-family:inherit;font-size:13px;color:var(--text-body);text-align:left;" onmouseover="this.style.background='var(--surface-alt)'" onmouseout="this.style.background='none'">
                                                        <x-avatar :user="$member->user" :size="20" /> {{ $member->user?->name }}
                                                    </button>
                                                @empty
                                                    <span style="display:block;padding:7px 10px;font-size:12.5px;color:var(--text-muted);">Semua anggota sudah jadi pelaksana</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                @if ($task->reviewer_id)
                                    <div style="display:flex;align-items:center;gap:7px;">
                                        <span style="color:var(--text-muted);">Reviewer</span>
                                        <x-avatar :user="$task->reviewer" :size="24" />
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($task->status === \App\Enum\Task\TaskStatus::REVIEW->value)
                            {{-- Kontrol review ini sebelumnya cuma ada di card Board - dipindah ke
                            modal juga (2026-09-18) supaya tetap bisa dipakai dari tampilan tabel
                            Penugasan Individu/Tim Saya yang nggak punya card. --}}
                            @if (($task->list_id ? ($task->reviewer_id ?? $task->created_by) : $task->created_by) === auth()->id())
                                <div style="background:var(--surface-alt);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:18px;">
                                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Tugas ini menunggu review kamu</p>
                                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                        <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ \App\Enum\Task\TaskStatus::IN_PROGRESS->value }}')" class="btn-ghost btn-sm">Kembalikan buat Revisi</button>
                                        <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ \App\Enum\Task\TaskStatus::COMPLETE->value }}')" class="btn-primary btn-sm">Setujui & Selesaikan</button>
                                    </div>
                                </div>
                            @else
                                <p style="font-size:12.5px;color:var(--text-muted);font-style:italic;margin-bottom:18px;">Menunggu penilaian.</p>
                            @endif
                        @endif

                        @if ($task->status === \App\Enum\Task\TaskStatus::COMPLETE->value)
                            @php
                                $ratedAssignees = $task->assignees->whereNotNull('rating');
                                $toRate = $task->requiresRatingFrom(auth()->id())
                                    ? $task->assignees->whereNull('rating')->where('user_id', '!=', auth()->id())
                                    : collect();
                            @endphp
                            <div style="margin-bottom:18px;">
                                @foreach ($ratedAssignees as $assignee)
                                    <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                                        @if ($task->assignees->count() > 1)
                                            <span style="font-size:12px;color:var(--text-muted);margin-right:6px;">{{ $assignee->user?->name }}</span>
                                        @endif
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="fa-solid fa-star" style="font-size:15px;color:{{ $i <= $assignee->rating ? '#f5a524' : 'var(--border-soft)' }};"></i>
                                        @endfor
                                        <span style="font-size:12px;color:var(--text-muted);margin-left:4px;">({{ $assignee->rating }}/5)</span>
                                    </div>
                                @endforeach
                                @if ($ratedAssignees->isEmpty() && $task->rating)
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="fa-solid fa-star" style="font-size:15px;color:{{ $i <= $task->rating ? '#f5a524' : 'var(--border-soft)' }};"></i>
                                        @endfor
                                        <span style="font-size:12px;color:var(--text-muted);margin-left:4px;">({{ $task->rating }}/5)</span>
                                    </div>
                                @endif
                                @if ($toRate->isNotEmpty())
                                    <div x-data="{ hover: 0 }" style="border:2px solid #f5a524;background:color-mix(in srgb, #f5a524 10%, transparent);border-radius:14px;padding:16px;text-align:center;margin-top:8px;">
                                        <p style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 2px;">Beri penilaian untuk {{ $toRate->count() > 1 ? 'semua pelaksana' : $toRate->first()->user?->name }} <span style="color:var(--danger-text);">*</span></p>
                                        <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">Wajib diisi sebelum tugas ini bisa ditutup.</p>
                                        <div style="display:flex;justify-content:center;gap:6px;">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <button type="button" wire:click="rateTask('{{ $task->id }}', {{ $i }})" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" aria-label="Beri {{ $i }} bintang" style="background:none;border:none;cursor:pointer;padding:4px;transition:transform 0.12s ease;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='none'">
                                                    <i class="fa-solid fa-star" :style="hover >= {{ $i }} ? 'color:#f5a524;' : 'color:var(--border);'" style="font-size:38px;"></i>
                                                </button>
                                            @endfor
                                        </div>
                                        @if ($toRate->count() > 1)
                                            {{-- 2 pelaksana: bisa dinilai sekaligus (bintang di atas) atau 1 per 1 (di bawah). --}}
                                            <p style="font-size:12px;color:var(--text-muted);margin:14px 0 8px;">atau nilai satu per satu:</p>
                                            @foreach ($toRate as $assignee)
                                                <div x-data="{ h: 0 }" style="display:flex;align-items:center;justify-content:center;gap:4px;margin-bottom:4px;">
                                                    <span style="font-size:12.5px;min-width:110px;text-align:right;margin-right:6px;">{{ $assignee->user?->name }}</span>
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <button type="button" wire:click="rateTask('{{ $task->id }}', {{ $i }}, '{{ $assignee->user_id }}')" @mouseenter="h = {{ $i }}" @mouseleave="h = 0" aria-label="Beri {{ $assignee->user?->name }} {{ $i }} bintang" style="background:none;border:none;cursor:pointer;padding:2px;">
                                                            <i class="fa-solid fa-star" :style="h >= {{ $i }} ? 'color:#f5a524;' : 'color:var(--border);'" style="font-size:20px;"></i>
                                                        </button>
                                                    @endfor
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endunless

                    @if ($task->isAssignee(auth()->id()) && $task->rejected_reason)
                        {{-- Catatan penolakan — cuma kelihatan buat pelaksana, bukan komentar publik. --}}
                        <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:12px;padding:14px 16px;margin-bottom:18px;">
                            <p style="font-size:14px;font-weight:700;color:var(--danger-soft-text);margin:0 0 4px;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Tinjauan Ditolak
                                @if ($task->rejectionNoteBy) oleh {{ $task->rejectionNoteBy->name }} @endif
                            </p>
                            <p style="font-size:14.5px;color:var(--danger-soft-text);margin:0;">{{ $task->rejected_reason }}</p>
                        </div>
                    @endif

                    <div style="margin-bottom:18px;padding-top:18px;border-top:1px solid var(--border-soft);">
                        @php
                            $isTaskCreator = auth()->id() === $task->created_by;
                            $isTaskAssignee = $task->isAssignee(auth()->id());
                            // Pelaksana, pembuat tugas, dan pembuat Board (khusus tugas Board) yang boleh mengelola
                            // checklist & lampiran - anggota lain cuma bisa melihat (Board arsip: lihat saja).
                            $isBoardOwner = $task->project_id && $this->isProjectOwner && ! $this->isProjectArchived;
                            $canToggleChecklist = ! $this->isProjectArchived && ($isTaskCreator || $isTaskAssignee || $isBoardOwner);
                            // Boleh menambah lampiran: yang mengelola tugas, atau anggota Board lain kalau pembuat mengaktifkannya di kartu.
                            $canAttach = $canToggleChecklist || ($task->project_id && $task->members_can_attach && ! $this->isProjectArchived);
                            // Pemberi tugas boleh edit/hapus item siapa pun. Pelaksana (bukan
                            // sekaligus pemberi tugas) cuma boleh edit/hapus item yang dia buat
                            // sendiri - tidak boleh utak-atik item bikinan pemberi tugas.
                            $canModifyItem = fn ($item) => ! $this->isProjectArchived && ($isTaskCreator || $isBoardOwner) || (! $this->isProjectArchived && $isTaskAssignee && ($item->created_by ?? $task->created_by) === auth()->id());
                            $checklistTotal = $task->checklistItems->count();
                            $checklistDone = $task->checklistItems->where('is_done', true)->count();
                        @endphp
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <h4 style="font-size:16px;font-weight:700;margin:0;">Checklist</h4>
                            @if ($checklistTotal > 0)
                                <span style="font-size:12px;font-weight:700;color:var(--text-muted);">{{ $checklistDone }}/{{ $checklistTotal }}</span>
                            @endif
                        </div>
                        @if ($checklistTotal > 0)
                            <div style="height:5px;border-radius:99px;background:var(--border-soft);overflow:hidden;margin-bottom:12px;">
                                <div style="height:100%;border-radius:99px;background:var(--brand-success);width:{{ round($checklistDone / $checklistTotal * 100) }}%;"></div>
                            </div>
                        @endif
                        @php $checklistAssignees = $task->assignees->map(fn ($a) => $a->user)->filter(); @endphp
                        {{-- data-checklist-list: diurutkan lewat drag (SortableJS, lihat initTaskBoardSortables). --}}
                        <div data-checklist-list data-task-id="{{ $task->id }}" data-sortable-enabled="{{ $canToggleChecklist ? 1 : 0 }}">
                        @forelse ($task->checklistItems as $item)
                            @php $canCheckItem = $canToggleChecklist && (! $item->assigned_to || $item->assigned_to === auth()->id() || $isBoardOwner); @endphp
                            <div wire:key="cl-{{ $item->id }}" data-item-id="{{ $item->id }}" class="task-checklist-row {{ $canToggleChecklist ? 'is-draggable' : '' }}" style="{{ $this->isNewItem($item->done_at, $item->done_by) ? "background:var(--new-item-bg);" : "" }}display:flex;align-items:center;gap:8px;margin-bottom:{{ $canToggleChecklist ? '6px' : '5px' }};{{ $canToggleChecklist ? 'cursor:grab;' : '' }}">
                                @if ($editingChecklistItemId === $item->id)
                                    <input type="text" wire:model="editingChecklistItemTitle" wire:keydown.enter="updateChecklistItem" wire:keydown.escape="cancelEditChecklistItem" class="form-input" style="font-size:13px;padding:4px 8px;height:auto;flex:1;" autofocus>
                                    <button type="button" wire:click="updateChecklistItem" style="background:none;border:none;color:var(--brand-success);cursor:pointer;"><i class="fa-solid fa-check"></i></button>
                                    <button type="button" wire:click="cancelEditChecklistItem" style="background:none;border:none;color:var(--text-faint);cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                                @else
                                    {{-- Cuma kotak centangnya yang memicu ceklis (bukan teks/barisnya), supaya geser-urut tidak kebetulan mencentang. --}}
                                    <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;flex:1;min-width:0;{{ $canCheckItem ? '' : 'opacity:0.6;' }}" @if ($item->assigned_to && ! $canCheckItem) title="Hanya {{ $item->assignee?->name }} yang bisa mencentang item ini" @endif>
                                        <label class="custom-checkbox task-checklist-checkbox" style="--accent:#042c6c;{{ $canCheckItem ? '' : 'cursor:not-allowed;' }}">
                                            <input type="checkbox" @if ($canCheckItem) wire:click="toggleChecklistItem('{{ $item->id }}')" @else disabled @endif @checked($item->is_done)>
                                            <span class="box"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                        <span style="min-width:0;">
                                            <span style="{{ $item->is_done ? 'text-decoration:line-through;color:var(--text-muted);' : '' }}">{{ $item->title }}</span>
                                            @if ($item->is_done && $item->done_at)
                                                <span style="display:block;font-size:11px;color:var(--text-faint);text-decoration:none;font-weight:400;">
                                                    Selesai{{ $item->doneBy ? ' oleh '.($item->doneBy->profile?->nickname ?: $item->doneBy->name) : '' }} &middot; {{ $item->done_at->translatedFormat('j M Y, H:i') }}
                                                </span>
                                            @endif
                                            @if ($item->due_date)
                                                <span style="display:table;margin-top:2px;padding:0 4px;border-radius:3px;font-size:10px;font-weight:400;line-height:1.5;{{ $dueStyle($item) ?: 'color:var(--text-faint);' }}"><i class="fa-regular fa-clock"></i> {{ $item->due_date->translatedFormat('j M Y, H:i') }}</span>
                                            @endif
                                        </span>
                                    </div>
                                    {{-- Penugasan: avatar pelaksana terpilih tampil di samping tombol ubah; klik (kalau berhak) untuk ganti/lepas. --}}
                                    @if ($canModifyItem($item) && $checklistAssignees->count() > 1)
                                        <div x-data="{ open: false }" @click.outside="open = false" style="position:relative;flex-shrink:0;">
                                            <button type="button" @click="open = ! open" style="background:none;border:none;padding:0;cursor:pointer;display:flex;" title="{{ $item->assignee ? 'Ditugaskan ke '.$item->assignee->name.' — klik untuk ganti' : 'Tugaskan ke satu pelaksana' }}">
                                                @if ($item->assignee)
                                                    <x-avatar :user="$item->assignee" :size="22" />
                                                @else
                                                    <span style="width:22px;height:22px;border-radius:50%;border:1.5px dashed var(--text-faint);display:flex;align-items:center;justify-content:center;color:var(--text-faint);"><i class="fa-solid fa-user-plus" style="font-size:9px;"></i></span>
                                                @endif
                                            </button>
                                            <div x-show="open" x-cloak style="display:none;position:absolute;right:0;top:100%;margin-top:4px;z-index:30;min-width:180px;padding:4px;background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 20px rgba(15,23,42,0.18);">
                                                <button type="button" wire:click="assignChecklistItem('{{ $item->id }}', '')" @click="open = false" style="display:flex;align-items:center;gap:8px;width:100%;padding:6px 8px;border:none;background:{{ $item->assigned_to ? 'none' : 'var(--surface-alt)' }};border-radius:7px;cursor:pointer;font-size:12.5px;color:var(--text);text-align:left;">
                                                    <i class="fa-solid fa-users" style="font-size:11px;width:20px;text-align:center;color:var(--text-muted);"></i> Semua pelaksana
                                                </button>
                                                @foreach ($checklistAssignees as $u)
                                                    <button type="button" wire:click="assignChecklistItem('{{ $item->id }}', '{{ $u->id }}')" @click="open = false" style="display:flex;align-items:center;gap:8px;width:100%;padding:6px 8px;border:none;background:{{ $item->assigned_to === $u->id ? 'var(--surface-alt)' : 'none' }};border-radius:7px;cursor:pointer;font-size:12.5px;color:var(--text);text-align:left;">
                                                        <x-avatar :user="$u" :size="20" /> {{ $u->profile?->nickname ?: $u->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif ($item->assignee)
                                        <span style="display:flex;flex-shrink:0;"><x-avatar :user="$item->assignee" :size="22" /></span>
                                    @endif
                                    @if ($canModifyItem($item))
                                        {{-- Tenggat item: ikon jam, input muncul sebagai tooltip saat di-hover (tetap terbuka selama input fokus). --}}
                                        <div x-data="{ open: false, focused: false }" @mouseenter="open = true" @mouseleave="open = focused" style="position:relative;flex-shrink:0;">
                                            <button type="button" style="background:none;border:none;color:{{ $item->due_date ? 'var(--primary,#2563eb)' : 'var(--text-faint)' }};cursor:pointer;padding:2px;" title="Tenggat item"><i class="fa-regular fa-clock" style="font-size:12px;"></i></button>
                                            <div x-show="open" x-cloak style="display:none;position:absolute;right:0;bottom:100%;padding-bottom:4px;z-index:30;">
                                                <input type="datetime-local" class="form-input" value="{{ $item->due_date?->format('Y-m-d\TH:i') }}" wire:change="setChecklistItemDueDate('{{ $item->id }}', $event.target.value)" @focus="focused = true" @blur="focused = false; open = false" style="width:auto;padding:3px 8px;font-size:12px;box-shadow:0 4px 14px rgba(0,0,0,.15);">
                                            </div>
                                        </div>
                                        <button type="button" wire:click="startEditChecklistItem('{{ $item->id }}', @js($item->title))" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:2px;" title="Ubah"><i class="fa-solid fa-pen" style="font-size:11px;"></i></button>
                                        <button type="button" wire:click="deleteChecklistItem('{{ $item->id }}')" wire:confirm="Hapus item checklist ini?" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:2px;" title="Hapus"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p style="font-size:14px;color:var(--text-muted);">Belum ada checklist.</p>
                        @endforelse
                        </div>
                        @if ($canToggleChecklist)
                            <div style="display:flex;gap:6px;margin-top:8px;">
                                <input type="text" class="form-input" style="font-size:12.5px;padding:6px 10px;" wire:model="newChecklistTitle" wire:keydown.enter.prevent="addChecklistItemToTask('{{ $task->id }}')" placeholder="Tambah item checklist...">
                                <button type="button" wire:click="addChecklistItemToTask('{{ $task->id }}')" class="btn-ghost btn-sm">Tambah</button>
                            </div>
                        @endif
                    </div>

                    {{-- Todo Saya (catatan pribadi, bukan tugas Board) nggak ada lampiran. --}}
                    @unless ($task->is_self_assigned && ! $task->project_id)
                    <div x-data="{ showAddAttachment: false, tab: 'file', drag: false }" style="margin-bottom:18px;padding-top:18px;border-top:1px solid var(--border-soft);">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <h4 style="font-size:16px;font-weight:700;margin:0;"><i class="fa-solid fa-paperclip"></i> Lampiran</h4>
                            @if ($canAttach)
                                <button type="button" @click="showAddAttachment = !showAddAttachment" class="btn-ghost btn-sm">Tambah</button>
                            @endif
                        </div>
                        {{-- Pengaturan kartu: anggota Board lain boleh melampirkan (di luar komentar). Cuma pembuat tugas / owner Board. --}}
                        @if ($task->project_id && ! $this->isProjectArchived && ($isTaskCreator || $isBoardOwner))
                            <div style="display:flex;align-items:center;gap:10px;margin:-4px 0 10px;">
                                <label class="custom-checkbox" style="--accent:#042c6c;">
                                    <input type="checkbox" @checked($task->members_can_attach) wire:click="setMembersCanAttach('{{ $task->id }}', {{ $task->members_can_attach ? 'false' : 'true' }})">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                                <span style="font-size:13px;color:var(--text-muted);">Anggota Board lain boleh menambah lampiran</span>
                            </div>
                        @endif

                        <div x-show="showAddAttachment && @js($canAttach)" x-cloak style="margin-bottom:14px;padding:12px;background:var(--surface-alt);border-radius:10px;">
                            <div style="display:flex;gap:4px;margin-bottom:12px;">
                                <button type="button" @click="tab = 'file'" class="btn-ghost btn-sm" :style="tab === 'file' ? 'background:var(--brand-fill);color:#fff;' : ''"><i class="fa-solid fa-file"></i> File</button>
                                <button type="button" @click="tab = 'link'" class="btn-ghost btn-sm" :style="tab === 'link' ? 'background:var(--brand-fill);color:#fff;' : ''"><i class="fa-solid fa-link"></i> Link</button>
                            </div>

                            {{-- Tab File: dropzone. File yang dijatuhkan dimasukkan ke input lalu event "change"
                                 ditembakkan supaya wire:model Livewire menangkapnya. --}}
                            <div x-show="tab === 'file'" x-data="{ progress: 0, uploading: false }"
                                x-on:livewire-upload-start="uploading = true; progress = 0"
                                x-on:livewire-upload-finish="uploading = false"
                                x-on:livewire-upload-error="uploading = false"
                                x-on:livewire-upload-progress="progress = $event.detail.progress">
                                @if ($newAttachmentFile)
                                    <div style="display:flex;align-items:center;gap:10px;padding:12px;border:1.5px solid var(--border);border-radius:12px;background:var(--surface);">
                                        <div style="width:38px;height:38px;border-radius:10px;background:var(--surface-alt);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="fa-solid fa-file-circle-check" style="font-size:16px;color:var(--brand-success);"></i>
                                        </div>
                                        <div style="min-width:0;flex:1;">
                                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $newAttachmentFile->getClientOriginalName() }}</p>
                                            <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ number_format($newAttachmentFile->getSize() / 1024, 1) }} KB</p>
                                        </div>
                                        <button type="button" wire:click="$set('newAttachmentFile', null)" class="btn-ghost" style="padding:6px 9px;flex-shrink:0;" title="Ganti file"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                @else
                                    <label x-data="{ drag: false }"
                                        @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
                                        @drop.prevent="drag = false; $refs.attFile.files = $event.dataTransfer.files; $refs.attFile.dispatchEvent(new Event('change', { bubbles: true }))"
                                        :style="drag ? 'border-color:var(--brand-fill);transform:scale(1.01);' : ''"
                                        style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:26px 14px;border:2px dashed var(--border);border-radius:14px;cursor:pointer;text-align:center;background:var(--surface);transition:all .15s;">
                                        <span style="width:46px;height:46px;border-radius:50%;background:var(--surface-alt);display:flex;align-items:center;justify-content:center;">
                                            <i class="fa-solid fa-cloud-arrow-up" style="font-size:20px;color:var(--brand-fill);"></i>
                                        </span>
                                        <span style="font-size:13px;font-weight:700;color:var(--text-heading);" x-text="drag ? 'Lepaskan file di sini' : 'Tarik & lepas file di sini'"></span>
                                        <span style="font-size:12px;color:var(--text-muted);">atau <span style="color:var(--brand-fill);font-weight:600;text-decoration:underline;">pilih dari perangkat</span></span>
                                        <span style="font-size:11px;color:var(--text-faint);">Foto dikompres otomatis &middot; file maks. 10 MB</span>
                                        <input type="file" x-ref="attFile" wire:model="newAttachmentFile" style="display:none;">
                                    </label>
                                @endif
                                <div x-show="uploading" x-cloak style="height:5px;border-radius:99px;background:var(--border);overflow:hidden;margin-top:8px;">
                                    <div :style="'width:' + progress + '%'" style="height:100%;background:var(--brand-fill);transition:width .15s;"></div>
                                </div>
                                @error('newAttachmentFile') <p style="font-size:11px;color:var(--danger-text);margin:6px 0 0;">{{ $message }}</p> @enderror
                            </div>

                            {{-- Tab Link --}}
                            <div x-show="tab === 'link'" x-cloak style="display:flex;flex-wrap:wrap;gap:8px;">
                                <input type="url" class="form-input" style="flex:2;min-width:180px;font-size:12.5px;padding:8px 10px;" wire:model="newAttachmentLink" placeholder="https://...">
                                <input type="text" class="form-input" style="flex:1;min-width:130px;font-size:12.5px;padding:8px 10px;" wire:model="newAttachmentLinkLabel" placeholder="Judul link (opsional)">
                                @error('newAttachmentLink') <p style="width:100%;font-size:11px;color:var(--danger-text);margin:0;">{{ $message }}</p> @enderror
                            </div>

                            <input type="text" class="form-input" style="width:100%;font-size:12.5px;padding:8px 10px;margin-top:10px;" wire:model="newAttachmentDescription" maxlength="255" placeholder="Keterangan lampiran (opsional)">
                            @error('newAttachmentDescription') <p style="font-size:11px;color:var(--danger-text);margin:4px 0 0;">{{ $message }}</p> @enderror

                            <div style="display:flex;justify-content:flex-end;margin-top:10px;">
                                <button type="button" x-show="tab === 'file'" wire:click="uploadFileAttachment" wire:loading.attr="disabled" wire:target="newAttachmentFile,uploadFileAttachment" @disabled(! $newAttachmentFile) class="btn-primary" style="padding:7px 16px;font-size:12px;"><i class="fa-solid fa-upload"></i> Unggah</button>
                                <button type="button" x-show="tab === 'link'" x-cloak wire:click="addLinkAttachment" class="btn-primary" style="padding:7px 16px;font-size:12px;"><i class="fa-solid fa-link"></i> Tambah Link</button>
                            </div>
                        </div>

                        @if ($task->attachments->isNotEmpty())
                            <p style="font-size:12px;font-weight:700;color:var(--text-muted);margin:0 0 8px;">Files</p>
                        @endif

                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @forelse ($task->attachments as $attachment)
                                @php
                                    $isImage = ! $attachment->isLink() && preg_match('/\.(jpe?g|png|gif|webp)$/i', $attachment->file_path ?? '');
                                    $ext = $attachment->isLink() ? 'LINK' : strtoupper(pathinfo($attachment->file_path ?? '', PATHINFO_EXTENSION) ?: 'FILE');
                                @endphp
                                <div x-data="{ open: false }" style="display:flex;align-items:center;gap:12px;padding:8px;border-radius:10px;background:var(--surface-alt);{{ $this->isNewItem($attachment->created_at, $attachment->uploaded_by) ? "background:var(--new-item-bg);" : "" }}">
                                    @if ($isImage)
                                        <img src="{{ $attachment->file_url }}" @click="open = true" style="width:48px;height:48px;object-fit:cover;border-radius:8px;cursor:pointer;flex-shrink:0;" alt="{{ $attachment->file_name }}">
                                        {{-- Lightbox: layout (flex tengah) di CSS class, bukan inline style - x-show membuang display inline saat tampil, bikin gambar nggak di tengah. --}}
                                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click="open = false" @keydown.escape.window="open = false" class="task-lightbox">
                                            <button type="button" @click.stop="open = false" class="task-lightbox-close" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
                                            <img src="{{ $attachment->file_url }}" @click.stop alt="{{ $attachment->file_name }}">
                                        </div>
                                    @else
                                        <span style="width:48px;height:48px;flex-shrink:0;border-radius:8px;background:var(--border-soft);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">{{ $ext }}</span>
                                    @endif

                                    <div style="flex:1;min-width:0;">
                                        @if ($attachment->isLink())
                                            <a href="{{ $attachment->url }}" target="_blank" rel="noopener" style="display:block;font-size:13.5px;font-weight:600;color:#1a56c4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $attachment->file_name ?? $attachment->url }}</a>
                                        @else
                                            <a href="{{ $attachment->file_url }}" target="_blank" rel="noopener" style="display:block;font-size:13.5px;font-weight:600;color:var(--text-heading);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $attachment->file_name ?? 'File' }}</a>
                                        @endif
                                        @if ($attachment->comment_id)
                                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:1px 7px;margin-top:2px;border-radius:99px;background:var(--brand-soft);color:var(--brand);"><i class="fa-regular fa-comment" style="font-size:9px;"></i> dari komentar</span>
                                        @endif
                                        @if (filled($attachment->description))
                                            <p style="font-size:12.5px;color:var(--text-body);margin:2px 0 2px;white-space:pre-line;word-break:break-word;">{{ $attachment->description }}</p>
                                        @endif
                                        <span style="display:block;font-size:11.5px;color:var(--text-muted);" title="{{ $attachment->created_at->translatedFormat('j F Y, H:i') }}">
                                            <i class="fa-solid fa-user-pen" style="font-size:10px;"></i>
                                            {{ $attachment->uploadedBy?->profile?->nickname ?: ($attachment->uploadedBy?->name ?? 'Pengguna dihapus') }}
                                            &middot; {{ $attachment->created_at->translatedFormat('j M Y, H:i') }}
                                            <span style="color:var(--text-faint);">({{ $attachment->created_at->diffForHumans() }})</span>
                                        </span>
                                    </div>

                                    @unless ($attachment->isLink())
                                        <a href="{{ $attachment->file_url }}" target="_blank" rel="noopener" class="btn-ghost" style="padding:6px 8px;flex-shrink:0;" title="Buka"><i class="fa-solid fa-up-right-from-square"></i></a>
                                    @endunless
                                    @if ($canToggleChecklist || ($canAttach && $attachment->uploaded_by === auth()->id()))
                                        <button type="button" wire:click="removeAttachment('{{ $attachment->id }}')" wire:confirm="Hapus lampiran ini?" class="btn-ghost" style="padding:6px 8px;flex-shrink:0;color:var(--danger-text);" title="Hapus"><i class="fa-solid fa-xmark"></i></button>
                                    @endif
                                </div>
                            @empty
                                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada lampiran.</p>
                            @endforelse
                        </div>
                    </div>
                    @endunless

                    {{-- Riwayat aktivitas: siapa melakukan apa & kapan (pindah List, status, ceklis, lampiran, rating). --}}
                    @unless ($isPersonalNote)
                        <div x-data="{ open: false }" style="margin-bottom:18px;padding-top:14px;border-top:1px solid var(--border-soft);">
                            <button type="button" @click="open = ! open" style="display:flex;align-items:center;gap:8px;width:100%;background:none;border:none;padding:0;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-muted);text-align:left;">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size:12px;"></i>
                                <span>Riwayat aktivitas ({{ $task->activities->count() }})</span>
                                <i class="fa-solid fa-chevron-down" :style="open && 'transform:rotate(180deg);'" style="font-size:10px;transition:transform 0.15s;margin-left:auto;"></i>
                            </button>
                            <div x-show="open" x-cloak style="margin-top:10px;">
                                <div class="task-list-scroll" style="display:flex;flex-direction:column;gap:10px;max-height:260px;overflow-y:auto;overscroll-behavior:contain;padding-right:6px;">
                                    @forelse ($task->activities->take(50) as $activity)
                                        <div style="display:flex;gap:9px;font-size:12.5px;">
                                            <span style="width:7px;height:7px;border-radius:50%;background:var(--border);margin-top:6px;flex-shrink:0;"></span>
                                            <div style="min-width:0;">
                                                <span style="font-weight:700;">{{ $activity->user?->profile?->nickname ?: ($activity->user?->name ?? 'Sistem') }}</span>
                                                <span>{{ lcfirst($activity->description) }}</span>
                                                <span style="display:block;font-size:11px;color:var(--text-faint);" title="{{ $activity->created_at->translatedFormat('j F Y, H:i:s') }}">{{ $activity->created_at->translatedFormat('j M Y, H:i') }} ({{ $activity->created_at->diffForHumans() }})</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada aktivitas tercatat.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endunless
                </div>

                @unless ($isPersonalNote)
                    {{-- Komentar ala Trello, kolom kanan terpisah — cuma buat task yang bukan self-assigned. --}}
                    <div class="task-detail-comments" style="flex:0 0 480px;position:relative;min-height:360px;border-left:1px solid var(--border-soft);padding-left:24px;">
                        {{-- Tinggi kolom komentar mengikuti tinggi kartu (mis. bertambah karena lampiran); kolom input tetap, sisanya jadi area daftar komentar yang scroll. --}}
                        <div class="task-detail-comments-inner" style="position:absolute;top:0;right:0;bottom:0;left:24px;display:flex;flex-direction:column;min-height:0;">
                        <h4 style="font-size:16px;font-weight:700;margin:0 0 12px;flex-shrink:0;"><i class="fa-regular fa-comment"></i> Komentar ({{ $task->comments->count() }})</h4>
                        @php
                            // Mention "@Nama": daftar nama buat dropdown (tanpa diri sendiri) & buat menebalkan "@Nama" di teks.
                            $mentionUsers = app(\App\Services\Task\TaskService::class)->mentionableUsers($task);
                            $taskService = app(\App\Services\Task\TaskService::class);
                            $mentionNames = $mentionUsers->flatMap(fn ($u) => $taskService->mentionTerms($u))->all();
                            // Dropdown: nama panggilan dulu (kalau ada), nama lengkap sebagai petunjuk.
                            $mentionPick = $mentionUsers->reject(fn ($u) => $u->id === auth()->id())
                                ->map(fn ($u) => ['label' => $taskService->mentionLabel($u), 'full' => $u->name])->values()->all();
                            $topComments = $task->comments->whereNull('parent_id')->sortByDesc('created_at'); // terbaru di atas; balasan tetap urut lama ke baru di bawah induknya
                            $repliesByParent = $task->comments->whereNotNull('parent_id')->groupBy('parent_id');
                            $replyingTo = $this->replyToId ? $task->comments->firstWhere('id', $this->replyToId) : null;
                        @endphp
                        <div style="margin-bottom:14px;flex-shrink:0;"
                            x-data="{
                                names: @js($mentionPick), open: false, q: '',
                                matches() { const q = this.q.toLowerCase(); return this.names.filter(n => n.label.toLowerCase().includes(q) || n.full.toLowerCase().includes(q)).slice(0, 6) },
                                check(el) {
                                    const m = el.value.slice(0, el.selectionStart).match(/(?:^|\s)@([^\s@]*)$/);
                                    this.q = m ? m[1] : '';
                                    this.open = !!m && this.matches().length > 0;
                                },
                                pick(el, name) {
                                    const pos = el.selectionStart;
                                    el.value = el.value.slice(0, pos).replace(/@([^\s@]*)$/, '@' + name + ' ') + el.value.slice(pos);
                                    el.dispatchEvent(new Event('input'));
                                    this.open = false;
                                    el.focus();
                                }
                            }">
                            @if ($replyingTo)
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                                    <i class="fa-solid fa-reply"></i>
                                    <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Membalas <strong>{{ $replyingTo->user?->name }}</strong>: {{ \Illuminate\Support\Str::limit($replyingTo->comment, 40) }}</span>
                                    <button type="button" wire:click="cancelReply" style="background:none;border:none;cursor:pointer;color:var(--text-muted);" aria-label="Batal membalas"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            @endif
                            @if ($newCommentFile)
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $newCommentFile->getClientOriginalName() }}</span>
                                    <button type="button" wire:click="$set('newCommentFile', null)" style="background:none;border:none;cursor:pointer;color:var(--text-muted);" aria-label="Batal lampiran"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            @endif
                            @error('newCommentFile') <p style="color:var(--danger-text);font-size:12px;margin:0 0 6px;">{{ $message }}</p> @enderror
                            <div style="display:flex;gap:8px;position:relative;align-items:flex-end;">
                                {{-- Textarea yang tinggi otomatis ke bawah mengikuti isi (maks 200px, lalu scroll). Enter kirim, Shift+Enter baris baru. --}}
                                <textarea id="task-comment-input" rows="1" class="form-input" style="font-size:13.5px;resize:none;overflow-y:auto;max-height:200px;line-height:1.4;" wire:model="newComment" autocomplete="off"
                                    x-init="$watch('$wire.newComment', () => $nextTick(() => { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px' }))"
                                    @input="check($el); $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'" @click="check($el)" @keyup.arrow-left="check($el)" @keyup.arrow-right="check($el)"
                                    @keydown.escape="open = false"
                                    @keydown.enter="if (open) { $event.preventDefault(); pick($el, matches()[0]) } else if ($event.ctrlKey || $event.metaKey) { $event.preventDefault(); $wire.addComment('{{ $task->id }}') }"
                                    placeholder="{{ $replyingTo ? 'Tulis balasan...' : 'Tulis komentar... (@ untuk menyebut, Ctrl+Enter untuk kirim)' }}"></textarea>
                                {{-- Lampirkan file/foto ke komentar; hasilnya tampil di komentar dan masuk daftar Lampiran. --}}
                                <label class="btn-ghost" style="padding:8px 10px;cursor:pointer;flex-shrink:0;" title="Lampirkan file atau foto" aria-label="Lampirkan file atau foto">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <input type="file" wire:model="newCommentFile" style="display:none;">
                                </label>
                                <button type="button" wire:click="addComment('{{ $task->id }}')" wire:loading.attr="disabled" wire:target="newCommentFile" class="btn-primary" style="font-size:13.5px;padding:8px 14px;">Kirim</button>
                                <div x-show="open" x-cloak @click.outside="open = false" style="position:absolute;top:calc(100% + 4px);left:0;z-index:60;min-width:200px;max-height:220px;overflow-y:auto;background:var(--surface);border-radius:12px;padding:6px;box-shadow:0 8px 30px rgba(4,44,108,0.16),0 0 0 1px var(--border-soft);">
                                    <template x-for="n in matches()" :key="n.full">
                                        <button type="button" @click="pick(document.getElementById('task-comment-input'), n.label)" style="display:block;width:100%;padding:7px 10px;border:none;background:none;border-radius:8px;cursor:pointer;font-family:inherit;font-size:13px;color:var(--text-body);text-align:left;" onmouseover="this.style.background='var(--surface-alt)'" onmouseout="this.style.background='none'">
                                            <span x-text="'@' + n.label"></span>
                                            <span x-show="n.label !== n.full" x-text="n.full" style="color:var(--text-muted);font-size:11.5px;margin-left:4px;"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="task-list-scroll" style="flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;display:flex;flex-direction:column;gap:14px;padding-right:6px;">
                            @forelse ($topComments as $comment)
                                <div style="font-size:13.5px;">
                                    <div style="display:flex;gap:9px;padding:8px 10px;border-radius:10px;background:var(--surface-alt);border:1px solid var(--border-soft);{{ $this->isNewItem($comment->created_at, $comment->user_id) ? 'border-left:3px solid var(--brand);' : '' }}">
                                        <x-avatar :user="$comment->user" :size="26" />
                                        <div style="min-width:0;">
                                            <span style="font-weight:700;">{{ $comment->user?->name }}</span>
                                            <span style="color:var(--text-muted);font-size:12px;"> &middot; {{ $comment->created_at->diffForHumans() }}</span>
                                            <p style="margin:2px 0 0;white-space:pre-line;word-break:break-word;">{{ $comment->bodyHtml($mentionNames) }}</p>
                                            @include("livewire.task.partials.comment-attachments", ["comment" => $comment])
                                            @unless ($this->isProjectArchived)
                                                <button type="button" wire:click="startReply('{{ $comment->id }}')" @click="$nextTick(() => document.getElementById('task-comment-input')?.focus())" style="background:none;border:none;padding:0;margin-top:2px;cursor:pointer;font-family:inherit;font-size:12px;font-weight:700;color:var(--text-muted);">Balas</button>
                                            @endunless
                                        </div>
                                    </div>
                                    {{-- Balasan: 1 tingkat saja - nggak ada tombol Balas di sini. --}}
                                    @foreach ($repliesByParent->get($comment->id, collect()) as $reply)
                                        <div style="display:flex;gap:8px;margin:6px 0 0 35px;padding:6px 10px;border-radius:10px;background:var(--surface-alt);border:1px solid var(--border-soft);{{ $this->isNewItem($reply->created_at, $reply->user_id) ? 'border-left:3px solid var(--brand);' : '' }}">
                                            <x-avatar :user="$reply->user" :size="22" />
                                            <div style="min-width:0;">
                                                <span style="font-weight:700;">{{ $reply->user?->name }}</span>
                                                <span style="color:var(--text-muted);font-size:12px;"> &middot; {{ $reply->created_at->diffForHumans() }}</span>
                                                <p style="margin:2px 0 0;white-space:pre-line;word-break:break-word;">{{ $reply->bodyHtml($mentionNames) }}</p>
                                                @include("livewire.task.partials.comment-attachments", ["comment" => $reply])
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <p style="font-size:13.5px;color:var(--text-muted);margin:0;">Belum ada komentar.</p>
                            @endforelse
                        </div>
                        </div>
                    </div>
                @endunless
            </div>

            @unless ($ratingRequired)
                <div style="display:flex;justify-content:flex-end;margin-top:18px;padding-top:14px;border-top:1px solid var(--border-soft);">
                    <button type="button" @click="$wire.closeTaskDetail()" class="btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Tutup</button>
                </div>
            @endunless
        </div>
    </div>
@endif

@if ($showMembersModal && $this->activeProject)
    <div class="modal-overlay" wire:click="closeMembersModal">
        <div class="modal-box" style="max-height:80vh;display:flex;flex-direction:column;" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-shrink:0;">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Anggota {{ $this->activeProject->name }}</h3>
                <button type="button" wire:click="closeMembersModal" class="btn-ghost" style="padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            @if ($this->canManageProjectMembers)
                <div style="flex-shrink:0;margin-bottom:8px;">
                    <label class="form-label" style="font-size:11.5px;margin-bottom:4px;">Cari orang dari perusahaan</label>
                    <select class="form-input" style="font-size:12.5px;padding:7px 10px;" wire:model.live="memberCompanyId">
                        <option value="">{{ $this->memberCompanies->firstWhere('id', auth()->user()->company_id)?->name ?? 'Perusahaan saya' }} (perusahaan saya)</option>
                        @foreach ($this->memberCompanies->where('id', '!=', auth()->user()->company_id) as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;gap:6px;align-items:flex-start;flex-shrink:0;margin-bottom:12px;">
                    <div wire:ignore wire:key="member-select-{{ $memberCompanyId ?: 'own' }}" style="flex:1;">
                        <select data-tom-select data-placeholder="-- Pilih orang --" wire:model="newProjectMemberId">
                            <option value=""></option>
                            @foreach ($this->companyUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" wire:click="addProjectMember" class="btn-primary btn-sm">Tambah</button>
                </div>
            @endif

            <div class="task-list-scroll" style="display:flex;flex-direction:column;gap:8px;max-height:min(320px, 50vh);overflow-y:auto;overscroll-behavior:contain;padding-right:6px;">
                @foreach ($this->projectMembers as $member)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <x-avatar :user="$member->user" :size="20" />
                            <span style="font-size:13px;">{{ $member->user?->name }}</span>
                            @if ($member->role === 'owner')
                                <span class="badge badge-approved">Owner</span>
                            @elseif ($member->is_assigner)
                                <span class="badge badge-info">Assigner</span>
                            @endif
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if ($this->canManageProjectMembers && $member->role !== 'owner')
                                <label class="custom-checkbox" style="--accent:#0f766e;" title="Boleh assign/bikin task untuk anggota lain">
                                    <input type="checkbox" @checked($member->is_assigner) wire:click="toggleAssigner('{{ $member->user_id }}', {{ $member->is_assigner ? 'false' : 'true' }})">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                                <button type="button" wire:click="removeProjectMember('{{ $member->user_id }}')" wire:confirm="Keluarkan anggota ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;">
                                    <i class="fa-solid fa-user-minus"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
@endif
</div>
