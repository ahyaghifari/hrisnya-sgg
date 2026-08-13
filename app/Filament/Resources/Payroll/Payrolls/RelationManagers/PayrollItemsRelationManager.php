<?php

namespace App\Filament\Resources\Payroll\Payrolls\RelationManagers;

use App\Enum\CustomPermissionEnum;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use App\Models\AuditLog;
use App\Models\Payroll\Payroll;
use App\Models\Payroll\PayrollItem;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollItemsRelationManager extends RelationManager
{
    protected $listeners = ['refreshRelation' => '$refresh'];
    protected static string $relationship = 'items';

    /** Snapshot amount/type SEBELUM EditAction menimpa record - dipakai after() buat hitung
     * delta ke total payroll (gross/deductions/net), karena by the time after() jalan, $record
     * sudah keburu ke-update ke nilai baru. */
    private ?float $editingOldAmount = null;

    private ?string $editingOldType = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Component')
                    ->searchable(),

                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => match (true) {
                        $state === null => '-',
                        $state === 'manual' => 'Manual',
                        default => PayrollComponentEnum::tryFrom($state)?->label() ?? $state,
                    }),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'earning',
                        'danger'  => 'deduction',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(0)
                    ->alignCenter()
                    ->placeholder('-'),

                TextColumn::make('unit')
                    ->label('Unit')
                    ->placeholder('-'),

                TextColumn::make('unit_amount')
                    ->label('Unit Amount')
                    ->money('IDR', true)
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IDR', true)
                    ->weight('bold')
                    ->color(fn ($record) =>
                    $record->type === 'earning'
                        ? 'success'
                        : 'danger'
                    ),
            ])
            ->headerActions(actions: [
                CreateAction::make()
                    ->label('Add Adjustment')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->authorize(fn () => Auth::user()->can(CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT->value))
                    ->modalHeading('Add Payroll Adjustment')
                    ->modalDescription('This item will be treated as a manual payroll adjustment.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Component Name')
                            ->required(),

                        Select::make('type')
                            ->options([
                                'earning' => 'Earning',
                                'deduction' => 'Deduction',
                            ])
                            ->required(),

                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix('IDR'),

                        Textarea::make('note')
                            ->label('Note')
                            ->required()
                            ->rows(3),
                    ])
                    ->mutateFormDataUsing(function (array $data) {
                        return array_merge($data, [
                            'quantity' => 1,
                            'unit' => null,
                            'unit_amount' => null,
                            'source_type' => 'manual',
                        ]);
                    })
                    ->after(function ($record, array $data, $livewire) {
                        $payroll = $this->getOwnerRecord();

                        $this->applyToPayrollTotals($payroll, $data['type'], (float) $data['amount']);
                        $this->recordAdjustmentLog($payroll, $record, null, (string) $record->amount, $data['note']);

                        $livewire->dispatch('refresh');
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn () => Auth::user()->can(CustomPermissionEnum::EDIT_PAYROLL_ADJUSTMENT->value))
                    ->modalHeading('Edit Payroll Item')
                    ->modalDescription('Perubahan pada baris ini (termasuk baris hasil kalkulator) akan tercatat ke audit log. Catatan alasan wajib diisi.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Component Name')
                            ->required(),

                        Select::make('type')
                            ->options([
                                'earning' => 'Earning',
                                'deduction' => 'Deduction',
                            ])
                            ->required(),

                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix('IDR'),

                        Textarea::make('note')
                            ->label('Catatan (alasan perubahan)')
                            ->required()
                            ->rows(3),
                    ])
                    ->before(function ($record) {
                        $this->editingOldAmount = (float) $record->amount;
                        $this->editingOldType = $record->type;
                    })
                    ->after(function ($record, array $data, $livewire) {
                        $payroll = $this->getOwnerRecord();

                        // Batalkan kontribusi nilai LAMA dulu (bisa jadi type-nya juga berubah,
                        // earning <-> deduction), baru tambahkan nilai BARU - biar benar juga
                        // kalau adminnya sekalian ganti earning jadi deduction atau sebaliknya.
                        $this->applyToPayrollTotals($payroll, $this->editingOldType, -$this->editingOldAmount);
                        $this->applyToPayrollTotals($payroll, $data['type'], (float) $data['amount']);

                        $this->recordAdjustmentLog(
                            $payroll,
                            $record,
                            (string) $this->editingOldAmount,
                            (string) $data['amount'],
                            $data['note'],
                        );

                        $livewire->dispatch('refresh');
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * @param float $amount Positif = tambahkan kontribusi ke total, negatif = batalkan.
     *
     * Read-modify-write PHP, BUKAN increment()/decrement() - keduanya generate SQL
     * `UPDATE col = col + ?` mentah di level DB, tidak kompatibel dengan kolom yang di-cast
     * PayrollEncrypted (nilai di kolom itu ciphertext, bukan angka, gak bisa dijumlah SQL).
     */
    private function applyToPayrollTotals(Payroll $payroll, string $type, float $amount): void
    {
        if ($type === 'earning') {
            $payroll->gross_salary += $amount;
            $payroll->net_salary += $amount;
        } else {
            $payroll->total_deductions += $amount;
            $payroll->net_salary -= $amount;
        }

        $payroll->save();
    }

    /**
     * Jejak "siapa ubah/nambah adjustment apa" — dicatat eksplisit di sini (bukan lewat trait
     * LogsAuditChanges di model PayrollItem), karena PayrollItem juga dibuat massal oleh
     * Calculator tiap Generate/Regenerate payroll - kalau trait ditempel ke modelnya, tiap
     * generate (ratusan baris per employee x semua employee) ikut kecatat, log jadi penuh
     * noise. Cuma jalur "Add Adjustment"/Edit manual ini yang dianggap penyesuaian.
     */
    private function recordAdjustmentLog(Payroll $payroll, PayrollItem $record, ?string $oldAmount, string $newAmount, string $note): void
    {
        AuditLog::record(
            'payroll_adjustment',
            $payroll->payrollPeriod?->company_id,
            'payroll_item:' . $record->id,
            $record->name . ' — ' . $payroll->user?->name . ' (' . $note . ')',
            $oldAmount,
            $newAmount,
        );
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->is_paid;
    }
}
