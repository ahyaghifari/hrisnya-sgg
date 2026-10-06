@if (($n = $this->unreadCounts[$task->id][$kind] ?? 0) > 0)
    <span title="{{ $n }} baru" style="background:#dc2626;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;padding:0 4px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;margin-left:2px;">{{ $n > 99 ? '99+' : $n }}</span>
@endif
