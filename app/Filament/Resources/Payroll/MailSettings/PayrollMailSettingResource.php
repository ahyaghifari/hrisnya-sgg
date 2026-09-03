<?php

namespace App\Filament\Resources\Payroll\MailSettings;

use App\Enum\CustomPermissionEnum;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Payroll\MailSettings\Pages\ManagePayrollMailSettings;
use App\Models\Payroll\PayrollMailSetting;
use App\Services\Payroll\PayrollMailer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Akun SMTP per perusahaan KHUSUS kirim slip gaji email (SendPayrollSlipJob lewat
 * App\Services\Payroll\PayrollMailer). Perusahaan tanpa baris aktif -> mailer default.
 * `password` dienkripsi (cast model). Admin-panel only.
 */
class PayrollMailSettingResource extends BaseCompanyResource
{
    protected static ?string $model = PayrollMailSetting::class;

    protected static ?string $navigationLabel = 'Email Slip Gaji';

    protected static ?string $modelLabel = 'Akun Email Slip Gaji';

    protected static ?string $pluralModelLabel = 'Akun Email Slip Gaji';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return static::isSuperAdmin()
            || static::user()->can(CustomPermissionEnum::EDIT_KOMPONEN_GAJI_GLOBAL->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                Toggle::make('is_active')
                    ->label('Aktifkan pengiriman lewat akun ini')
                    ->default(true),

                TextInput::make('host')
                    ->label('SMTP Host')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('smtp.gmail.com'),

                TextInput::make('port')
                    ->label('Port')
                    ->numeric()
                    ->required()
                    ->default(587)
                    ->minValue(1)
                    ->maxValue(65535),

                Select::make('encryption')
                    ->label('Enkripsi')
                    ->options(['tls' => 'TLS', 'ssl' => 'SSL'])
                    ->placeholder('Tidak ada'),

                TextInput::make('username')
                    ->label('Username')
                    ->required()
                    ->maxLength(255)
                    ->autocomplete(false),

                TextInput::make('password')
                    ->label('Password / App Password')
                    ->password()
                    ->revealable()
                    ->maxLength(1024)
                    ->autocomplete('new-password')
                    ->helperText('Disimpan terenkripsi. Untuk Gmail pakai App Password. Kosongkan saat edit kalau tidak ingin mengubah.')
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state)),

                TextInput::make('from_address')
                    ->label('Email Pengirim (From)')
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('from_name')
                    ->label('Nama Pengirim')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('HRD RSU Syifa Barabai'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),

                TextColumn::make('host')->label('Host')->searchable(),
                TextColumn::make('from_address')->label('From')->searchable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_tested_at')
                    ->label('Tes Terakhir')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->description(fn (PayrollMailSetting $r) => $r->last_test_error ? 'GAGAL: ' . Str::limit($r->last_test_error, 60) : null),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                Action::make('sendTest')
                    ->label('Kirim Tes')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->schema([
                        TextInput::make('recipient')
                            ->label('Kirim email tes ke')
                            ->email()
                            ->required()
                            ->default(fn () => static::user()->email),
                    ])
                    ->action(function (PayrollMailSetting $record, array $data) {
                        $result = app(PayrollMailer::class)->test($record, $data['recipient']);

                        $record->update([
                            'last_tested_at' => now(),
                            'last_test_error' => $result['ok'] ? null : Str::limit($result['message'], 500),
                        ]);

                        Notification::make()
                            ->title($result['ok'] ? 'Email tes terkirim' : 'Tes gagal')
                            ->body($result['message'])
                            ->{$result['ok'] ? 'success' : 'danger'}()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayrollMailSettings::route('/'),
        ];
    }
}
