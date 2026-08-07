<?php

namespace App\Livewire\LMS;

use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Padanan portal dari admin LmsCoursesResource (/admin/lms/lms-courses) — daftar pelatihan
 * LMS. Sengaja cuma list dulu (belum Tambah/Edit) — form admin-nya sangat besar (tab
 * Jadwal/Pertemuan/Peserta/Umpan Balik/Penilaian Trainer, lihat LmsCoursesForm), jadi
 * create/edit portalnya dikerjakan menyusul terpisah.
 *
 * Tidak ada filter/kolom Perusahaan — semua pelatihan saat ini punya company_id holding,
 * scoping tetap dijaga di baseQuery() (kalau nanti company lain mulai punya pelatihan
 * sendiri, tinggal munculkan lagi filternya). Cuma pelatihan internal (source_type='internal')
 * yang ditampilkan — konsisten dengan keputusan kategori (LmsCategories) yang juga khusus
 * internal, jadi tidak ada filter/kolom Tipe.
 *
 * Sengaja TIDAK pakai trait Livewire\WithPagination (beda dari kebiasaan Livewire pada
 * umumnya) — trait itu meng-override path resolver paginator jadi path relatif tanpa
 * leading slash, yang di-resolve browser sebagai dobel segment (mis. /lms/lms/pelatihan).
 * Pola list lain di project ini (KaryawanPage, dst) juga tidak pakai trait ini, cukup
 * paginate() biasa — reset ke halaman 1 otomatis terjadi tiap filter berubah karena request
 * Livewire AJAX tidak membawa query string `page` dari GET awal.
 */
#[Layout('components.layout', ['title' => 'Pelatihan'])]
class LmsCoursesPage extends Component
{
    // Pagination di sini pakai wire:navigate (lihat livewire/partials/pagination.blade.php),
    // yaitu full page-visit yang bikin komponen di-mount ULANG dari URL. Tanpa #[Url], filter
    // ini ikut hilang begitu pindah halaman paginasi.
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public ?string $categoryId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('viewAny', LmsCourses::class), 403);

        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        } elseif ($this->companyId === null) {
            $this->companyId = Auth::user()->company_id;
        }
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    protected function baseQuery()
    {
        $query = LmsCourses::query()
            ->with('category')
            ->withCount('courseUsers')
            ->where('source_type', 'internal');

        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        } elseif (! Auth::user()->canFilterByCompany()) {
            $query->where('company_id', Auth::user()->company_id);
        }

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function getCoursesProperty()
    {
        return $this->baseQuery()->orderByDesc('created_at')->paginate(10)->appends([
            'companyId' => $this->companyId,
            'categoryId' => $this->categoryId,
            'search' => $this->search,
        ]);
    }

    public function render()
    {
        return view('livewire.lms.lms-courses-page');
    }
}
