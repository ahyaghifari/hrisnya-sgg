<div class="border border-gray-300 rounded-lg">
    @if($isEmpty)
        <div class="flex flex-col items-center justify-center py-12">
            {{-- <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg> --}}
            <p class="text-gray-500 text-lg font-medium">Belum ada soal yang dipilih</p>
            <p class="text-gray-400 text-sm mt-1">Pilih soal dari dropdown di atas untuk melihat preview</p>
        </div>
    @else
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <!-- Header Table -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-500 text-white p-2 rounded-lg">
                            {{-- <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg> --}}
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Daftar Soal Terpilih</h3>
                            <p class="text-sm text-gray-600">Preview soal untuk kuis ini</p>
                        </div>
                    </div>
                    <div class="bg-white px-4 py-2 rounded-full border border-blue-200">
                        <span class="text-blue-600 font-semibold">{{ $questions->count() }} Soal</span>
                    </div>
                </div>
            </div>

            <br><br>
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20 border-b border-gray-200">
                                No.
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                Pertanyaan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($questions as $index => $question)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap border-b border-black">
                                    <div class="flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full font-semibold text-sm">
                                        {{ $index + 1 }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 border-b border-black">
                                    <div class="prose prose-sm max-w-none">
                                        <div class="text-gray-900 leading-relaxed">
                                            {!! $question->question !!}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <br><br>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        {{-- <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg> --}}
                        <span>Urutan soal akan mengikuti urutan pemilihan dari dropdown</span>
                    </div>
                    <div class="text-sm font-medium text-gray-700">
                        Total: {{ $questions->count() }} soal
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>