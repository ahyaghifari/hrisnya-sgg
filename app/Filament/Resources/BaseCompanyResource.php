<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base untuk resource yang datanya merujuk company_id (beda dari CompaniesResource sendiri,
 * yang row-nya company - lihat komentar di sana). Super Admin lihat lintas company + dapat
 * kolom/filter/field "Perusahaan"; Admin dibatasi company sendiri, kolom/filter/field itu
 * disembunyikan (companyFormField() tetap punya ->default() walau disembunyikan, jadi
 * company_id tetap ke-dehidrasi otomatis ke company milik Admin baik saat create MAUPUN edit -
 * lihat App\Filament\Resources\BaseRumahSakitResource untuk pola sumbernya).
 */
abstract class BaseCompanyResource extends BaseResource
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::canBrowseAllCompanies()) {
            return $query;
        }

        return $query->where('company_id', static::companyId());
    }

    public static function companyFormField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('company_id')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->label('Perusahaan')
            ->default(fn () => static::companyId())
            ->visible(fn () => static::canBrowseAllCompanies());
    }

    public static function companyTableColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('company.name')
            ->label('Perusahaan')
            ->searchable()
            ->sortable()
            ->visible(fn () => static::canBrowseAllCompanies());
    }

    public static function companyTableFilter(): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('company_id')
            ->relationship('company', 'name')
            ->label('Perusahaan')
            ->preload()
            ->searchable()
            ->visible(fn () => static::canBrowseAllCompanies());
    }
}
