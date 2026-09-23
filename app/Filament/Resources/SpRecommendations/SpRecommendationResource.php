<?php

namespace App\Filament\Resources\SpRecommendations;

use App\Filament\Resources\SpRecommendations\Pages\ListSpRecommendations;
use App\Filament\Resources\SpRecommendations\Tables\SpRecommendationsTable;
use App\Helpers\PermissionHelper;
use App\Models\Sp\SpRecommendation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Review HR atas rekomendasi SP dari Coaching (planning/2026-09-23-sp-dan-merit-point.md §2.4).
 * Cuma List — tidak ada Create/Edit, recommendation cuma dibuat lewat CoachingDetailPage
 * (section Tindakan Lanjutan), HR di sini cuma Setujui/Tolak (row action, lihat SpRecommendationsTable).
 */
class SpRecommendationResource extends Resource
{
    protected static ?string $model = SpRecommendation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Pembinaan';

    protected static ?string $navigationLabel = 'Review Rekomendasi SP';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return SpRecommendationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpRecommendations::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
