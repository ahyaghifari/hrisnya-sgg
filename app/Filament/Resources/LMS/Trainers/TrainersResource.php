<?php

namespace App\Filament\Resources\LMS\Trainers;

use App\Filament\Resources\LMS\Trainers\Pages\ManageTrainers;
use App\Models\LMS\Trainer\Trainers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Sebelumnya model ini TIDAK punya Resource/Policy sama sekali (dropdown "Pilih Trainer" di
 * form Meeting cuma listing, tidak ada create/edit di mana pun) — dibuat sekarang murni biar
 * Shield auto-generate permission ViewAny/View/Create/Update/Delete:Trainers + Policy, yang
 * lalu dipakai halaman portal "Pemateri" (App\Livewire\LMS\TrainersPage). Bukan buat dipakai
 * sebagai UI utama (itu di portal), tapi tetap fungsional kalau perlu diakses dari /admin.
 */
class TrainersResource extends Resource
{
    protected static ?string $model = Trainers::class;

    protected static ?string $navigationLabel = 'Pemateri';

    protected static ?string $modelLabel = 'Pemateri';

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'internal' => 'Internal',
                        'external' => 'External',
                    ])
                    ->native(false)
                    ->default('external')
                    ->required(),

                Select::make('user_id')
                    ->label('Karyawan')
                    ->helperText('Isi kalau pemateri ini karyawan sendiri (tipe Internal).')
                    ->options(fn () => \App\Models\User::orderByName()->get()->pluck('name', 'id'))
                    ->searchable(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                TextInput::make('expertise')
                    ->label('Keahlian')
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(255),

                TextInput::make('company')
                    ->label('Perusahaan Asal')
                    ->maxLength(255),

                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                // Jumlah PELATIHAN (course distinct), bukan jumlah baris pertemuan — 1 pelatihan
                // bisa punya beberapa pertemuan dengan trainer yang sama.
                'meetings as trainings_count' => fn ($q) => $q->select(DB::raw('count(distinct course_id)')),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'internal' ? 'info' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('trainings_count')
                    ->label('Jumlah Pelatihan')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('expertise')
                    ->label('Keahlian')
                    ->placeholder('-')
                    ->limit(40),

                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('-'),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->visible(fn ($record) => static::canEdit($record)),

                // Pemateri yang sudah pernah mengisi pelatihan tidak boleh dihapus (riwayat
                // pertemuan/penilaiannya ikut hilang) — cuma bisa dinonaktifkan.
                Action::make('toggleActive')
                    ->hiddenLabel()
                    ->label(fn (Trainers $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (Trainers $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Trainers $record) => $record->is_active ? 'warning' : 'success')
                    ->visible(fn (Trainers $record) => static::canEdit($record) && $record->trainings_count > 0)
                    ->action(fn (Trainers $record) => $record->update(['is_active' => ! $record->is_active])),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (Trainers $record) => static::canDelete($record) && $record->trainings_count === 0),
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
            'index' => ManageTrainers::route('/'),
        ];
    }
}
