<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Payroll\PayrollPeriods\Pages\ManagePayrollPeriods;
use App\Jobs\GeneratePayrollJob;
use App\Jobs\RegeneratePayrollJob;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PayrollPeriodResource extends BaseCompanyResource
{
    protected static ?string $model = PayrollPeriod::class;

    protected static ?string $navigationLabel = "Periode Penggajian";

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                DatePicker::make('start_date')
                    ->label('Dari Tanggal')
                    ->required(),

                DatePicker::make('end_date')
                    ->label('Sampai Tanggal')
                    ->required()
                    ->afterOrEqual('start_date'),

                Select::make('signer_id')
                    ->label('Penandatangan')
                    ->relationship('signer', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Nama yang muncul di bagian "Dibuat oleh" slip gaji - terpisah dari siapa yang menekan Generate.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),

                TextColumn::make('start_date')
                    ->label('Dari Tanggal')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Sampai Tanggal')
                    ->date()
                    ->sortable(),

                TextColumn::make('processed_at')
                    ->label('Status Generate')
                    ->formatStateUsing(fn ($state) => $state ? $state->translatedFormat('d M Y H:i') : 'Belum digenerate')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('update')
                    ->visible(fn ($record) => is_null($record->processed_at))
                    ->using(function ($record, array $data): PayrollPeriod {
                        $exists = PayrollPeriod::query()
                            ->where('company_id', $data['company_id'])
                            ->where('id', '!=', $record->id)
                            ->where(function ($q) use ($data) {
                                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']]);
                            })
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('Payroll period already exists in the selected date range.')
                                ->danger()
                                ->send();
                            throw new Halt();
                        }

                        $record->update($data);

                        return $record;
                    }),

                // Penandatangan & Tanggal Bayar murni informasional (tidak dipakai perhitungan
                // payroll), jadi sengaja TIDAK ikut abort/visible(processed_at) seperti
                // EditAction di atas — tetap bisa diubah kapan saja, termasuk pasca-generate.
                Action::make('editSignerAndPayment')
                    ->label('Penandatangan')
                    ->icon('heroicon-o-pencil-square')
                    ->authorize('update')
                    ->schema([
                        DatePicker::make('payment_date')
                            ->label('Tanggal Bayar'),
                        Select::make('signer_id')
                            ->label('Penandatangan')
                            ->relationship('signer', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Nama yang muncul di bagian "Dibuat oleh" slip gaji - terpisah dari siapa yang menekan Generate.'),
                    ])
                    ->fillForm(fn ($record) => [
                        'payment_date' => $record->payment_date,
                        'signer_id' => $record->signer_id,
                    ])
                    ->action(function ($record, array $data) {
                        $record->update($data);

                        Notification::make()
                            ->title('Penandatangan & tanggal bayar berhasil diubah')
                            ->success()
                            ->send();
                    }),

                Action::make('generatePayroll')
                    ->label('Generate Payroll')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Payroll')
                    ->modalDescription('This will generate payroll for all active users in this company. This action cannot be undone.')
                    ->visible(fn ($record) => is_null($record->processed_at))
                    ->action(function ($record) {
                        GeneratePayrollJob::dispatch($record->id, auth()->id());

                        Notification::make()
                            ->title('Payroll generation started')
                            ->body('Payroll is being processed in background.')
                            ->success()
                            ->send();
                    }),

                Action::make('regeneratePayroll')
                    ->label('Regenerate Payroll')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Payroll')
                    ->modalDescription('This will delete and re-generate all unpaid payrolls for this period. Payrolls that have already been paid will not be affected.')
                    ->visible(fn ($record) => ! is_null($record->processed_at))
                    ->action(function ($record) {
                        RegeneratePayrollJob::dispatch($record->id, auth()->id());

                        Notification::make()
                            ->title('Payroll regeneration started')
                            ->body('Unpaid payrolls are being re-generated in the background.')
                            ->warning()
                            ->send();
                    }),

                // Langkah 1 dari 2 kalau mau hapus periode yang sudah ada payroll dibayar -
                // set is_paid=false semua payroll di periode ini, supaya guard di deletePeriod
                // (di bawah) lolos. Dipisah dari deletePeriod sendiri (bukan bypass langsung)
                // supaya ada jeda/konfirmasi eksplisit sebelum status "sudah dibayar" dicabut.
                Action::make('unmarkAsPaid')
                    ->label('Batalkan Status Dibayar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Status Dibayar')
                    ->modalDescription('Ini akan menandai SEMUA payroll di periode ini sebagai BELUM dibayar, walau sudah pernah ditandai dibayar sebelumnya. Dipakai kalau memang mau menghapus periode ini nanti. Tidak menghapus data payroll-nya, cuma status is_paid.')
                    ->modalSubmitActionLabel('Ya, Batalkan Status Dibayar')
                    ->visible(fn ($record) => $record->payrolls()->where('is_paid', true)->exists())
                    ->action(function ($record) {
                        $count = $record->payrolls()->where('is_paid', true)->update(['is_paid' => false]);

                        Notification::make()
                            ->title('Status dibayar dibatalkan')
                            ->body("{$count} payroll di periode ini ditandai belum dibayar.")
                            ->warning()
                            ->send();
                    }),

                // Bersih total: hapus payrolls (payroll_items ikut cascade DB) +
                // payroll_manual_lines (cascade DB dari payroll_period_id) + periode-nya
                // sendiri. Ditolak kalau ada payroll is_paid=true di periode ini — payroll yang
                // sudah dibayar tidak boleh disentuh, prinsip yang sama dipakai regenerate.
                Action::make('deletePeriod')
                    ->label('Hapus Periode')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Periode Payroll')
                    ->modalDescription('Ini akan menghapus PERMANEN periode ini beserta semua payroll, item, dan manual line (Koperasi/Bon/Insentif) di dalamnya. Tidak bisa dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus Semua')
                    ->visible(fn ($record) => ! $record->payrolls()->where('is_paid', true)->exists())
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            Payroll::where('payroll_period_id', $record->id)->each(fn ($payroll) => $payroll->delete());
                            $record->delete();
                        });

                        Notification::make()
                            ->title('Periode berhasil dihapus')
                            ->body('Payroll, item, dan manual line di periode ini sudah ikut terhapus.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayrollPeriods::route('/'),
        ];
    }
}
