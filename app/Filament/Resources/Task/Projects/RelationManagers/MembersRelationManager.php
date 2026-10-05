<?php

namespace App\Filament\Resources\Task\Projects\RelationManagers;

use App\Models\Task\TaskProjectMember;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Anggota';

    protected static ?string $modelLabel = 'Anggota';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Karyawan')->required()->searchable()
                ->getSearchResultsUsing(fn (string $search) => User::query()->searchNameOrNip($search)->limit(50)->get()->pluck('name', 'id')->all())
                ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('project_id', $this->getOwnerRecord()->getKey()))
                ->disabledOn('edit'),
            Select::make('role')->label('Peran')->required()->default(TaskProjectMember::ROLE_MEMBER)
                ->options([TaskProjectMember::ROLE_OWNER => 'Owner', TaskProjectMember::ROLE_MEMBER => 'Anggota']),
            Toggle::make('is_assigner')->label('Boleh menugaskan')->helperText('Hanya relevan untuk Anggota; Owner selalu boleh.'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')->label('Karyawan')->placeholder('Pengguna dihapus'),
                TextColumn::make('role')->label('Peran')->badge()
                    ->formatStateUsing(fn ($state) => $state === TaskProjectMember::ROLE_OWNER ? 'Owner' : 'Anggota'),
                IconColumn::make('is_assigner')->label('Penugas')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
