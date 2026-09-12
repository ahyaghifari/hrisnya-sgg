@props(['companies', 'label' => 'Perusahaan', 'system' => null])

{{--
    $system (opsional, App\Enum\CustomPermissionEnum::SISTEM_*) - kalau diisi, dicek lewat
    canFilterByCompanyForSystem() (permission + config CompanyFilterAccess per sistem). Kalau
    tidak diisi, fallback ke canFilterByCompany() lama (halaman yang belum dipetakan ke sistem
    manapun, mis. Task Settings - lihat knowledge/markdown/permission-lihat-perusahaan-lain-dan-filter-perusahaan.md).
--}}
@if ($system ? auth()->user()->canFilterByCompanyForSystem($system) : auth()->user()->canFilterByCompany())
<div>
    <label class="form-label">{{ $label }}</label>
    <select {{ $attributes->merge(['class' => 'form-input', 'style' => 'min-width:220px;']) }}>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}">{{ $company->name }}</option>
        @endforeach
    </select>
</div>
@endif
