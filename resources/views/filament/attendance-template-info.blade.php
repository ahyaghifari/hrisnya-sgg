<div class="space-y-2">
    <p class="text-sm text-gray-600">
        File Excel harus memiliki kolom: <strong>nip, date, check_in_time, check_out_time, status</strong>
    </p>
    <div class="flex items-center space-x-2">
        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <a href="{{ route('attendance.template.download') }}" 
           class="text-sm text-blue-600 hover:text-blue-800 underline"
           target="_blank">
            Download Template Excel
        </a>
    </div>
</div>