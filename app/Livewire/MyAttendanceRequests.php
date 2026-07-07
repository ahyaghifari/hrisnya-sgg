<?php

namespace App\Livewire;

use App\Enum\AttendanceReqStatus;
use App\Models\Attendance\AttendanceRequests;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout', ['title' => 'Riwayat Pengajuan'])]
class MyAttendanceRequests extends Component implements HasTable, HasActions, HasSchemas
{
    use InteractsWithTable;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function mount(): void
    {
        abort_if(Auth::user()->canBrowseAllCompanies(), 403);
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(function () use ($user) {
                return AttendanceRequests::query()
                    ->where('user_id', $user->id)
                    ->with(['leaveDetails', 'permissionDetails', 'overtimeDetails']);
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime()
                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('j F Y H:i')),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'leave' => 'info',
                        'permission' => 'warning',
                        'overtime' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->state(fn (AttendanceRequests $record) => $this->keterangan($record))
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->color(fn (string $state): string => AttendanceReqStatus::from($state)->getColor())
                    ->icon(fn (string $state): string => AttendanceReqStatus::from($state)->getIcon())
                    ->formatStateUsing(fn (string $state): string => AttendanceReqStatus::from($state)->getLabel()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label('Detail')
                    ->url(fn (AttendanceRequests $record) => route('my-attendance-requests.show', ['record' => $record])),
            ]);
    }

    private function keterangan(AttendanceRequests $record): string
    {
        return match ($record->type) {
            'leave' => $record->leaveDetails->first()?->leave_type ?? 'Cuti',
            'permission' => $record->permissionDetails->first()?->permission_type ?? 'Izin',
            'overtime' => $record->overtimeDetails->first()?->overtime_type ?? 'Lembur',
            default => '-',
        };
    }

    public function render()
    {
        return view('livewire.my-attendance-requests');
    }
}
