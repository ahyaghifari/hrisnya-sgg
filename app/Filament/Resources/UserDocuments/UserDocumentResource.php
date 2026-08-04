<?php

namespace App\Filament\Resources\UserDocuments;

use App\Enum\UserDocumentType;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\UserDocuments\Pages\ManageUserDocuments;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserDocuments;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserDocumentResource extends BaseCompanyResource
{
    protected static ?string $model = UserDocuments::class;

    protected static ?string $navigationLabel = 'Dokumen';

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    public static function getModelLabel(): string
    {
        return 'Dokumen Karyawan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_filter')
                    ->label('Perusahaan')
                    ->columnSpanFull()
                    ->searchable()
                    ->preload()
                    ->options(fn () => Companies::orderBy('name')->pluck('name', 'id')->toArray())
                    ->placeholder('Pilih Perusahaan')
                    ->reactive()
                    ->dehydrated(false)
                    ->visible(fn () => static::isSuperAdmin())
                    ->afterStateUpdated(fn (callable $set) => $set('user_id', null)),

                Select::make('user_id')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih Karyawan')
                    ->reactive()
                    ->options(function (Get $get) {
                        $companyId = static::isSuperAdmin() ? $get('company_filter') : static::companyId();

                        return User::query()
                            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                            ->orderByDesc('is_active')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($user) => [
                                $user->id => $user->is_active ? $user->name : "{$user->name} (Nonaktif)",
                            ])
                            ->toArray();
                    })
                    ->afterStateUpdated(fn ($state, callable $set) => $set('user_name', User::find($state)?->name)),

                Select::make('document_type')
                    ->label('Tipe Dokumen')
                    ->required()
                    ->options(collect(UserDocumentType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->reactive(),

                TextInput::make('document_number')
                    ->label('Nomor Dokumen')
                    ->required(),

                DatePicker::make('issued_date')
                    ->label('Tanggal Diterbitkan')
                    ->required(),

                DatePicker::make('details.retirement_date')
                    ->label('Tanggal Pensiun')
                    ->visible(fn (Get $get) => $get('document_type') === UserDocumentType::RETIREMENT->value),

                Select::make('details.retirement_type')
                    ->label('Tipe Pensiun')
                    ->options([
                        'normal' => 'Pensiun Normal',
                        'early' => 'Pensiun Dini',
                        'disability' => 'Pensiun Cacat',
                    ])
                    ->visible(fn (Get $get) => $get('document_type') === UserDocumentType::RETIREMENT->value),

                Textarea::make('details.reason')
                    ->label('Alasan')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => in_array($get('document_type'), [
                        UserDocumentType::RESIGNATION->value,
                        UserDocumentType::RETIREMENT->value,
                    ])),

                TextInput::make('details.set_in')
                    ->label('Ditetapkan di')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('document_type') === UserDocumentType::RETIREMENT->value),

                Hidden::make('user_name'),
                Hidden::make('uploaded_by_user')->default(false),

                FileUpload::make('file_path')
                    ->label('Unggah Dokumen')
                    ->required()
                    ->columnSpanFull()
                    ->openable(true)
                    ->disk('public')
                    ->maxSize(10240)
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->directory(fn (Get $get) => 'user-documents/' . ($get('user_id') ?? 'unknown'))
                    ->visibility('public'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.company.name')->label('Perusahaan')->sortable()->visible(fn () => static::isSuperAdmin()),
                TextColumn::make('user_name')->label('Nama Karyawan')->searchable(),
                TextColumn::make('document_type')->label('Tipe Dokumen')->badge()
                    ->formatStateUsing(fn (?string $state) => UserDocumentType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('document_number')->label('Nomor Dokumen')->searchable(),
                TextColumn::make('issued_date')->label('Tanggal Dikeluarkan')->date('d-m-Y')->sortable(),
            ])
            ->filters([
                 SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('user.company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? null : Auth::user()->company_id)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
                SelectFilter::make('document_type')
                    ->label('Tipe Dokumen')
                    ->options(collect(UserDocumentType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->url(fn (UserDocuments $record) => $record->file_url, shouldOpenInNewTab: true)
                    ->visible(fn (UserDocuments $record) => filled($record->file_url)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = static::getModel()::query()->with(['user.company']);

        if (! static::isSuperAdmin()) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', static::companyId()));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserDocuments::route('/'),
        ];
    }
}
