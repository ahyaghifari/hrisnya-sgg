@props(['icon', 'title', 'sub' => null, 'meta' => null])

<div class="ms-head">
    <div class="ms-head-icon"><i class="fa-solid {{ $icon }}"></i></div>
    <div style="min-width:0;">
        <p class="ms-head-title">{{ $title }}</p>
        @if ($sub)
            <p class="ms-head-sub">{{ $sub }}</p>
        @endif
    </div>
    @if ($meta !== null)
        <span class="ms-head-meta">{{ $meta }}</span>
    @endif
</div>
