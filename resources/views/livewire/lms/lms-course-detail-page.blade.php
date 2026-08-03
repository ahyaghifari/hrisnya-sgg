@use('App\Enum\CourseUserStatus')
@use('App\Enum\LsmCourseStatus')

<div>
    <style>
        .lms-course-tab { padding: 10px 4px; margin-right: 22px; font-size: 13px; font-weight: 600; color: var(--text-faint); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-family: inherit; }
        .lms-course-tab.is-active { color: #042c6c; border-bottom-color: #042c6c; }
        html.dark .lms-course-tab.is-active { color: #6ea8e0; border-bottom-color: #6ea8e0; }
        .lms-course-schedule-badge { color: #0f766e; background: rgba(15,118,110,0.06); }
        html.dark .lms-course-schedule-badge { color: #5eead4; background: rgba(45,212,191,0.16); }
    </style>

    <x-page-header title="{{ $course->title }}" subtitle="{{ $course->code }} - {{ $course->category?->name ?? '-' }}" :back="route('pelatihan.kelola')" />

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:24px;margin-bottom:22px;">
        <div>
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">Tanggal Pelaksanaan</p>
            @if ($course->start_date)
                <span class="lms-course-schedule-badge" style="display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;border-radius:8px;padding:6px 12px;">
                    <i class="fa-solid fa-calendar-days"></i>
                    {{ \Carbon\Carbon::parse($course->start_date)->translatedFormat('l, d M Y') }}
                    @if ($course->end_date && $course->end_date !== $course->start_date)
                        &ndash; {{ \Carbon\Carbon::parse($course->end_date)->translatedFormat('l, d M Y') }}
                    @endif
                </span>
            @else
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum dijadwalkan</p>
            @endif
        </div>

        <div>
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">Kompetensi yang Didapatkan</p>
            @if ($course->competencies->isNotEmpty())
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach ($course->competencies as $competency)
                        <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $competency->name }}</span>
                    @endforeach
                </div>
            @else
                <p style="font-size:13px;color:var(--text-muted);margin:0;">-</p>
            @endif
        </div>

        <div>
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">Kategori / Jenis Pelatihan</p>
            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">
                {{ $course->category?->name ?? '-' }} / {{ $course->category?->training_category?->label() ?? '-' }}
            </p>
        </div>

        <div>
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">Total Waktu / JP Pelatihan</p>
            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">
                @if ($this->totalDurationMinutes > 0)
                    {{ intdiv($this->totalDurationMinutes, 60) }} jam {{ $this->totalDurationMinutes % 60 }} menit
                @else
                    -
                @endif
                / {{ $this->totalJp > 0 ? $this->totalJp . ' JP' : '-' }}
            </p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:14px 18px;margin-bottom:18px;display:flex;flex-wrap:wrap;align-items:center;gap:14px;justify-content:space-between;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;">
            @php $statusCase = LsmCourseStatus::tryFrom($course->status); @endphp
            @if ($statusCase)
                <span class="badge" style="background:{{ $statusCase->badgeColor()['bg'] }};color:{{ $statusCase->badgeColor()['text'] }};font-weight:700;">
                    {{ $statusCase->label() }}
                </span>
            @endif

            @if ($statusCase === LsmCourseStatus::Draft)
                <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:12px;">
                    <span style="display:inline-flex;align-items:center;gap:5px;color:{{ $this->publishChecklist['hasParticipant'] ? '#16a34a' : 'var(--text-faint)' }};">
                        <i class="fa-solid {{ $this->publishChecklist['hasParticipant'] ? 'fa-circle-check' : 'fa-circle' }}"></i>
                        Minimal 1 peserta
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:5px;color:{{ $this->publishChecklist['meetingsComplete'] ? '#16a34a' : 'var(--text-faint)' }};">
                        <i class="fa-solid {{ $this->publishChecklist['meetingsComplete'] ? 'fa-circle-check' : 'fa-circle' }}"></i>
                        Semua pertemuan sudah punya pemateri &amp; jam
                    </span>
                </div>
            @endif
        </div>

        <div style="display:flex;gap:8px;">
            @if ($course->requires_feedback && $course->feedback_public_token)
                <button type="button" wire:click="showFeedbackQr" class="btn-ghost" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-qrcode"></i> QR Umpan Balik
                </button>
            @endif

            @if ($statusCase === LsmCourseStatus::Draft && $this->canPublish && $this->canEditDetail)
                <button type="button" wire:click="publishCourse" wire:confirm="Publish pelatihan ini? Setelah dipublish, pelatihan akan tampil ke peserta." class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-paper-plane"></i> Publish
                </button>
            @endif
        </div>
    </div>

    @if ($qrModalUrl)
        <div class="modal-overlay" wire:click.self="closeQrModal">
            <div style="background:var(--surface);border-radius:14px;padding:24px;max-width:360px;width:100%;text-align:center;margin-top:10vh;">
                <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin-bottom:14px;">{{ $qrModalTitle }}</p>
                <img src="{{ $qrModalUrl }}" alt="QR Code" style="width:100%;max-width:280px;margin:0 auto 14px;display:block;">
                <p style="font-size:11px;color:var(--text-muted);word-break:break-all;margin-bottom:16px;">{{ $qrModalLink }}</p>
                <button type="button" wire:click="closeQrModal" class="btn-ghost" style="padding:8px 18px;font-size:12.5px;">Tutup</button>
            </div>
        </div>
    @endif

    @if ($quizResultsMeetingQuizId)
        <div class="modal-overlay" wire:click.self="closeQuizResultsModal">
            <div style="background:var(--surface);border-radius:14px;padding:24px;max-width:680px;width:100%;margin-top:6vh;max-height:85vh;overflow-y:auto;">
                @if ($quizResultsMode === 'summary')
                    <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin-bottom:16px;">
                        <i class="fa-solid fa-chart-simple" style="color:var(--text-faint);"></i> Rangkuman Hasil Kuis
                    </p>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:14px;margin-bottom:8px;">
                        <div style="background:var(--surface-muted);border-radius:10px;padding:14px;">
                            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">
                                <i class="fa-solid fa-users"></i> Total Peserta
                            </p>
                            <p style="font-size:20px;font-weight:800;color:var(--text-heading);margin:0 0 8px;">{{ $this->quizResultsSummary['total'] }}</p>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <span class="badge badge-cancelled"><i class="fa-solid fa-id-badge"></i> {{ $this->quizResultsSummary['employee_count'] }} Karyawan</span>
                                <span class="badge" style="background:#dbeafe;color:#1d4ed8;"><i class="fa-solid fa-globe"></i> {{ $this->quizResultsSummary['public_count'] }} Publik</span>
                            </div>
                        </div>
                        <div style="background:var(--surface-muted);border-radius:10px;padding:14px;">
                            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">
                                <i class="fa-solid fa-chart-line"></i> Rata-rata Skor
                            </p>
                            <p style="font-size:20px;font-weight:800;color:var(--text-heading);margin:0;">{{ $this->quizResultsSummary['avg_score'] }}</p>
                        </div>
                        <div style="background:var(--surface-muted);border-radius:10px;padding:14px;">
                            <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">
                                <i class="fa-solid fa-arrow-up-wide-short"></i> Tertinggi / Terendah
                            </p>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <span class="badge badge-approved">{{ $this->quizResultsSummary['max_score'] }}</span>
                                <span class="badge badge-cancelled">{{ $this->quizResultsSummary['min_score'] }}</span>
                            </div>
                        </div>
                        @if ($this->quizResultsSummary['pending_review'] > 0)
                            <div style="background:var(--surface-muted);border-radius:10px;padding:14px;">
                                <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;">
                                    <i class="fa-solid fa-pen-to-square"></i> Esai Belum Dinilai
                                </p>
                                <span class="badge badge-pending">{{ $this->quizResultsSummary['pending_review'] }} menunggu</span>
                            </div>
                        @endif
                    </div>
                @else
                    <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin-bottom:16px;">Hasil Kuis — Seluruh Peserta &amp; Responden</p>

                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;">
                            <thead>
                                <tr style="border-bottom:1.5px solid var(--border);">
                                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Nama</th>
                                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Asal</th>
                                    <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Skor</th>
                                    <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Benar/Salah</th>
                                    <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->quizResults as $result)
                                    <tr style="border-bottom:1px solid var(--border-soft);">
                                        <td style="padding:8px 10px;font-weight:600;white-space:nowrap;">{{ $result->respondent?->name ?? '-' }}</td>
                                        <td style="padding:8px 10px;white-space:nowrap;">
                                            @if ($result->respondent?->user_id)
                                                <span class="badge badge-cancelled"><i class="fa-solid fa-id-badge"></i> Karyawan</span>
                                            @else
                                                <span class="badge" style="background:#dbeafe;color:#1d4ed8;"><i class="fa-solid fa-globe"></i> Publik</span>
                                            @endif
                                        </td>
                                        <td style="padding:8px 10px;text-align:center;white-space:nowrap;">{{ $result->total_score }}/{{ $result->max_score }}</td>
                                        <td style="padding:8px 10px;text-align:center;white-space:nowrap;">{{ $result->correct_count }}/{{ $result->wrong_count }}</td>
                                        <td style="padding:8px 10px;text-align:center;white-space:nowrap;">
                                            @if ($result->status === 'pending_review')
                                                <span class="badge badge-pending"><i class="fa-solid fa-pen-to-square"></i> Belum Dinilai</span>
                                            @else
                                                <span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Selesai</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding:20px;text-align:center;color:var(--text-faint);">Belum ada yang mengerjakan kuis ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                    <button type="button" wire:click="closeQuizResultsModal" class="btn-ghost" style="padding:8px 18px;font-size:12.5px;">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    <div style="border-bottom:1px solid var(--border-soft);margin-bottom:18px;">
        <button type="button" wire:click="setTab('peserta')" class="lms-course-tab {{ $tab === 'peserta' ? 'is-active' : '' }}">Peserta</button>
        <button type="button" wire:click="setTab('pertemuan')" class="lms-course-tab {{ $tab === 'pertemuan' ? 'is-active' : '' }}">Pertemuan</button>
        @if ($course->requires_feedback)
            <button type="button" wire:click="setTab('umpan-balik')" class="lms-course-tab {{ $tab === 'umpan-balik' ? 'is-active' : '' }}">Umpan Balik</button>
        @endif
        <button type="button" wire:click="setTab('penilaian')" class="lms-course-tab {{ $tab === 'penilaian' ? 'is-active' : '' }}">Penilaian dan Gaji Pemateri</button>
    </div>

    @if ($tab === 'peserta')
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;margin-bottom:14px;">
            <div style="position:relative;">
                <label class="form-label">Cari</label>
                <input type="search" wire:model.live.debounce.300ms="participantSearch" class="form-input" placeholder="Nama peserta..." style="min-width:220px;">
            </div>

            @if (! $showAddParticipantForm && $this->canEditDetail)
                <button type="button" wire:click="openAddParticipantForm" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-user-plus"></i> Tambah Peserta
                </button>
            @endif
        </div>

        @if ($showAddParticipantForm)
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
                <label class="form-label">Pilih Karyawan</label>
                <div wire:ignore>
                    <select data-tom-select data-placeholder="Pilih karyawan..." multiple wire:model="selectedUserIds">
                        @foreach ($this->availableUserOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('selectedUserIds') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeAddParticipantForm" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="addParticipants" class="btn-primary">Simpan</button>
                </div>
            </div>
        @endif

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Peserta</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->participants as $participant)
                        <tr wire:key="'participant-'.{{ $participant->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;">{{ $participant->user?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;">
                                <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ CourseUserStatus::tryFrom($participant->status)?->getLabel() ?? $participant->status }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @if ($this->canEditDetail || $this->canApproveEnrollment)
                                        @if ($participant->status !== CourseUserStatus::APPROVED->value)
                                            <button type="button" wire:click="approveParticipant('{{ $participant->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:var(--brand-success);" title="Setujui">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        @endif
                                        @if ($participant->status !== CourseUserStatus::CANCELED->value)
                                            <button type="button" wire:click="cancelParticipant('{{ $participant->id }}')" wire:confirm="Batalkan keikutsertaan peserta ini?" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Batalkan">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        @endif
                                    @endif
                                    @if ($this->canEditDetail && $statusCase !== LsmCourseStatus::Published)
                                        <button type="button" wire:click="removeParticipant('{{ $participant->id }}')" wire:confirm="Hapus peserta ini dari pelatihan?" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:#dc2626;" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="padding:24px;text-align:center;color:var(--text-faint);">{{ trim($participantSearch) !== '' ? 'Tidak ada peserta yang cocok untuk pencarian ini.' : 'Belum ada peserta.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif ($tab === 'pertemuan')
        @if (! $this->isSingleDayCourse)
            <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
                @if (! $showAddMeetingForm && $this->canEditDetail)
                    <button type="button" wire:click="openAddMeetingForm" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Tambah Pertemuan
                    </button>
                @endif
            </div>
        @endif

        @if ($showAddMeetingForm)
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
                <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Pertemuan Baru</h4>
                @include('livewire.lms.partials.meeting-detail-form')
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeAddMeetingForm" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveMeeting" class="btn-primary">Simpan</button>
                </div>
            </div>
        @endif

        @if ($this->isSingleDayCourse)
            {{-- 1 hari = 1 pertemuan, tanpa collapse — langsung ke Detail Pertemuan (Trainer, dst). --}}
            @php $meeting = $this->meetings->first(); @endphp
            @if ($meeting)
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                    @include('livewire.lms.partials.meeting-sections')
                </div>
            @else
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-faint);">Belum ada pertemuan.</div>
            @endif
        @else
            <div style="display:flex;flex-direction:column;gap:10px;">
                @forelse ($this->meetings as $meeting)
                    @php $isExpanded = $expandedMeetingId === $meeting->id; @endphp
                    <div wire:key="'meeting-'.{{ $meeting->id }}" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
                        <button type="button" wire:click="toggleMeetingAccordion('{{ $meeting->id }}')" style="width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;background:none;border:none;cursor:pointer;font-family:inherit;text-align:left;">
                            <div>
                                <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ $meeting->title }}</p>
                                <p style="font-size:11.5px;color:var(--text-faint);margin:2px 0 0;">
                                    {{ $meeting->start_date ? \Carbon\Carbon::parse($meeting->start_date)->translatedFormat('d M Y') : '-' }}
                                    @if ($meeting->end_date && $meeting->end_date !== $meeting->start_date)
                                        &ndash; {{ \Carbon\Carbon::parse($meeting->end_date)->translatedFormat('d M Y') }}
                                    @endif
                                    @if ($meeting->start_time)
                                        &middot; {{ substr($meeting->start_time, 0, 5) }}@if ($meeting->end_time)-{{ substr($meeting->end_time, 0, 5) }}@endif
                                    @endif
                                    @if ($meeting->jp)
                                        &middot; {{ $meeting->jp }} JP
                                    @endif
                                    @if ($meeting->trainer?->name)
                                        &middot; {{ $meeting->trainer->name }}
                                    @endif
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-down" style="color:var(--text-faint);transition:transform .15s;{{ $isExpanded ? 'transform:rotate(180deg);' : '' }}"></i>
                        </button>

                        @if ($isExpanded)
                            <div style="padding:0 18px 18px;border-top:1px solid var(--border-soft);padding-top:16px;">
                                @include('livewire.lms.partials.meeting-sections')
                            </div>
                        @endif
                    </div>
                @empty
                    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-faint);">Belum ada pertemuan.</div>
                @endforelse
            </div>
        @endif
    @elseif ($tab === 'umpan-balik')
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-star" style="color:#eab308;font-size:18px;"></i>
            <div>
                <p style="font-size:18px;font-weight:700;color:var(--text-heading);margin:0;">{{ $this->averageRating }}</p>
                <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Rata-rata penilaian dari {{ $this->feedbacks->count() }} peserta</p>
            </div>
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Peserta</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Rating</th>
                        <th style="text-align:left;padding:10px 14px;">Komentar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->feedbacks as $feedback)
                        <tr wire:key="'feedback-'.{{ $feedback->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $feedback->respondent?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;">{{ $feedback->rating }}</td>
                            <td style="padding:9px 14px;">{{ $feedback->feedback ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada umpan balik.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'penilaian')
        @include('livewire.lms.partials.meeting-honorarium-tab')
    @endif
</div>
