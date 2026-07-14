<?php

namespace App\Filament\Resources\UserDocuments;

use App\Enum\UserDocumentType;
use App\Filament\Clusters\EmployeeDocument\EmployeeDocumentCluster;
use App\Filament\Resources\UserDocuments\Pages\CreateUserDocument;
use App\Filament\Resources\UserDocuments\Pages\EditUserDocument;
use App\Filament\Resources\UserDocuments\Pages\ListUserDocuments;
use App\Filament\Resources\UserDocuments\Schemas\UserDocumentForm;
use App\Filament\Resources\UserDocuments\Tables\UserDocumentsTable;
use App\Helpers\PermissionHelper;
use App\Models\User\UserDocuments;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserDocumentResource extends Resource
{
    protected static ?string $model = UserDocuments::class;

    protected static ?string $cluster = EmployeeDocumentCluster::class;

    // Dokumen karyawan sekarang dilihat dari portal (tab "Dokumen" di halaman Data
    // Diri/MyStaff), bukan dari admin panel. Resource + route tetap ada (belum ada upload
    // dari portal) supaya HC/admin masih bisa unggah dokumen lewat /admin/user-documents
    // langsung, tapi tidak lagi muncul di navigasi.
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    public static function form(Schema $schema): Schema
    {
        return UserDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserDocumentsTable::configure($table);
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
            'index' => ListUserDocuments::route('/'),
            'create' => CreateUserDocument::route('/create'),
            'edit' => EditUserDocument::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with([
            'user.company',
        ]);

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        return $query;
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Pengunduran Diri')
                ->label('Pengunduran Diri')
                ->url(static::getUrl('index', ['type' => UserDocumentType::RESIGNATION->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserDocumentType::RESIGNATION->value),

            NavigationItem::make('Surat Pensiun')
                ->label('Surat Pensiun')
                ->url(static::getUrl('index', ['type' => UserDocumentType::RETIREMENT->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserDocumentType::RETIREMENT->value),

            NavigationItem::make('Surat Tugas')
                ->label('Surat Tugas')
                ->url(static::getUrl('index', ['type' => UserDocumentType::ASSIGNMENT->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserDocumentType::ASSIGNMENT->value),

            NavigationItem::make('Surat Keterangan Kerja')
                ->label('Surat Keterangan Kerja')
                ->url(static::getUrl('index', ['type' => UserDocumentType::WORK_CERTIFICATE->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserDocumentType::WORK_CERTIFICATE->value),

            NavigationItem::make('Surat Kontrak Kerja')
                ->label('Surat Kontrak Kerja')
                ->url(static::getUrl('index', ['type' => UserDocumentType::EMPLOYMENT_CONTRACT->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserDocumentType::EMPLOYMENT_CONTRACT->value),
        ];
    }
}
