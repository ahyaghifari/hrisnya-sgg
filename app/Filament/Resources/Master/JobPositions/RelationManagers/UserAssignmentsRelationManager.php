<?php

namespace App\Filament\Resources\Master\JobPositions\RelationManagers;

use App\Models\User;
use App\Services\User\UserService;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'userAssignments';

    protected static ?string $title = 'Daftar User';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Nama User')
                    ->searchable()
                    ->required()
                    ->columnSpanFull()
                    ->placeholder('Pilih User')
                    ->preload()
                    ->options(function () {
                        $companyId = $this->ownerRecord->company_id;
                        $query = User::query();
                        if ($companyId) {
                            $query->where('company_id', $companyId);
                        }
                        return $query->orderBy('name')->pluck('name', 'id')->toArray();
                    }),
                Hidden::make('start_date')
                    ->default(now()->toDateString())
                    ->columnSpanFull(),
                Hidden::make('status')
                    ->default('active')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Actual Manpower: ' . ($this->ownerRecord->actual_manpower ?? 0) . ' | Total User: ' . $this->ownerRecord->userAssignments()->count() . ' | Kuota: ' . (($this->ownerRecord->actual_manpower ?? 0) - $this->ownerRecord->userAssignments()->count()))
            ->recordTitleAttribute('userAssignments')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama')
                    ->searchable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah User')
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Tambah User ke Jabatan')
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('create-action')
                            ->label('Simpan')
                            ->requiresConfirmation()
                            ->modalHeading('Konfirmasi Simpan Data')
                            ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                            ->action(function (array $mountedActions) {
                                $data = $mountedActions[0]->getRawData();
                            
                                $this->ownerRecord->userAssignments()->create($data);
                                app(UserService::class)->updateUserProfile(
                                    User::find($data['user_id']),
                                    [
                                        'job_position_id' => $this->ownerRecord->id,
                                        'org_structure_id' => $this->ownerRecord->org_structure_id,
                                        'pangkat_id' => $this->ownerRecord->pangkat_id,
                                        'golongan_id' => $this->ownerRecord->golongan_id,
                                        'kelas_id' => $this->ownerRecord->kelas_id,
                                    ]
                                );
    
                                Notification::make()
                                    ->title('Data berhasil disimpan')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->visible(function () {
                        return app(\App\Services\JobPosition\JobPositionService::class)
                            ->canAddUserToJobPosition($this->ownerRecord);
                    }),
            ])
            ->recordActions([
                // EditAction::make(),
                DeleteAction::make()
                    ->action(function (array $mountedActions) {
                        $data = $mountedActions[0]->getRecord();
                    
                        app(UserService::class)->updateUserProfile(
                            User::find($data->user_id),
                            [
                                'job_position_id' => null,
                                'org_structure_id' => null,
                                'pangkat_id' => null,
                                'golongan_id' => null,
                                'kelas_id' => null,
                            ]
                        );

                        $data->delete();

                        Notification::make()
                            ->title('Data berhasil dihapus')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
