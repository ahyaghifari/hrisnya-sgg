<?php

namespace App\Filament\Resources\Assessment\Assessments\RelationManagers;

use App\Filament\Resources\Assessment\UserAssessments\UserAssessmentResource;
use App\Models\Assessment\AssessmentUsers;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserAssessmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'userAssessments';

    protected static ?string $title = "Daftar Karyawan Dinilai";

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('userAssessments')
            ->columns([
                TextColumn::make('target.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('target.nip')
                    ->label('NIP Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('final_score')
                    ->label('Nilai Akhir')
                    ->formatStateUsing(fn ($state) => number_format($state, 0))
                    ->sortable(),

                TextColumn::make('final_category')
                    ->label('Kategori')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('userAssessment')
                    ->label('Buat Penilaian Karyawan')
                    ->modalHeading('Buat Penilaian Karyawan')
                    ->modalDescription('Buat penilaian untuk karyawan yang dipilih.')
                    ->schema([
                        Select::make('target_id')
                            ->label('Pilih Karyawan')
                            ->relationship('target')
                            ->options(function () {
                                $companyId = Auth::user()->company_id;
                                return \App\Models\User::where('company_id', $companyId)
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, Action $action) {
                        $assessmentId = $this->ownerRecord->id;
                        $targetId = $data['target_id'];

                        $userAssessment = UserAssessmentResource::getModel()::create([
                            'assessment_id' => $assessmentId,
                            'target_id' => $targetId,
                            'created_by' => Auth::id(),
                        ]);

                        $action->successRedirectUrl(
                            UserAssessmentResource::getUrl('edit', ['record' => $userAssessment->id])
                        );
                    })
            ])
            ->recordActions([
                Action::make('edit')
                    ->hiddenLabel()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('primary')
                    ->url(fn (AssessmentUsers $record): string => UserAssessmentResource::getUrl('edit', ['record' => $record->id])),
            ])
            ->toolbarActions([
                // 
            ]);
    }
}
