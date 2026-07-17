@props(['companies', 'label' => 'Perusahaan'])

<div>
    <label class="form-label">{{ $label }}</label>
    <select {{ $attributes->merge(['class' => 'form-input', 'style' => 'min-width:220px;']) }}>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}">{{ $company->name }}</option>
        @endforeach
    </select>
</div>
