<?php

namespace App\Filament\Resources\SpRecommendations\Tables;

use App\Filament\Resources\UserColumn;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpRecommendationDecision;
use App\Models\Sp\SpRecommendation;
use App\Services\Sp\SpService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SpRecommendationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->sortable(query: UserColumn::sortName())
                    ->searchable(query: UserColumn::searchName()),

                TextColumn::make('sp_level')
                    ->label('Level (Saran)')
                    ->badge(),

                TextColumn::make('reason.name')
                    ->label('Alasan')
                    ->searchable(),

                TextColumn::make('source_type')
                    ->label('Sumber'),

                TextColumn::make('reason_note')
                    ->label('Catatan')
                    ->limit(60)
                    ->tooltip(fn (SpRecommendation $record) => $record->reason_note),

                TextColumn::make('decision')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('decision')
                    ->label('Status')
                    ->options(collect(SpRecommendationDecision::cases())->mapWithKeys(fn ($d) => [$d->value => $d->getLabel()]))
                    ->default(SpRecommendationDecision::PENDING->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (SpRecommendation $record) => $record->decision === SpRecommendationDecision::PENDING)
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Rekomendasi SP')
                    ->schema([
                        Select::make('sp_level')
                            ->label('Level SP')
                            ->options(collect(SpLevel::cases())->mapWithKeys(fn ($l) => [$l->value => $l->getLabel()]))
                            ->default(fn (SpRecommendation $record) => $record->sp_level->value)
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->default(today())
                            ->required(),
                    ])
                    ->action(function (SpRecommendation $record, array $data) {
                        app(SpService::class)->approveRecommendation(
                            $record,
                            SpLevel::from($data['sp_level']),
                            $data['start_date'],
                            Auth::id()
                        );

                        Notification::make()->title('SP disetujui dan diterbitkan')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (SpRecommendation $record) => $record->decision === SpRecommendationDecision::PENDING)
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Rekomendasi SP')
                    ->modalDescription('SP tidak akan diterbitkan. Aksi ini tidak bisa dibatalkan.')
                    ->action(function (SpRecommendation $record) {
                        app(SpService::class)->rejectRecommendation($record, Auth::id());

                        Notification::make()->title('Rekomendasi ditolak')->success()->send();
                    }),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
