<?php

namespace App\Filament\Resources\Task\Tasks\Schemas;

use App\Enum\Task\TaskPriority;
use App\Enum\Task\TaskStatus;
use App\Models\Task\Task;
use App\Models\Task\TaskList;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->disabled()->dehydrated(false),
            TextInput::make('requirement')->label('Judul Tugas')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->label('Deskripsi')->rows(4)->columnSpanFull(),

            Select::make('status')->label('Status')
                ->options(collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all())
                ->required()
                ->helperText('Untuk tugas Board, status sebaiknya sejalan dengan List (List Review = Review, List Selesai = Selesai).'),
            Select::make('priority')->label('Prioritas')
                ->options(collect(TaskPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->getLabel()])->all())
                ->required(),
            DateTimePicker::make('due_date')->label('Tenggat'),
            Select::make('recurrence')->label('Pengulangan')->options(Task::RECURRENCES)
                ->placeholder('Tidak berulang')
                ->helperText('Hanya berlaku untuk tugas Board yang punya tenggat.')
                ->visible(fn (?Task $record) => (bool) $record?->project_id),
            Select::make('list_id')->label('List')
                ->options(fn (?Task $record) => $record?->project_id
                    ? TaskList::where('project_id', $record->project_id)->orderBy('position')->pluck('name', 'id')->all()
                    : [])
                ->visible(fn (?Task $record) => (bool) $record?->project_id),

            self::userSelect('assigned_to', 'Pelaksana Utama'),
            self::userSelect('reviewer_id', 'Reviewer'),

            Toggle::make('is_overdue')->label('Terlambat')->helperText('Diisi otomatis sistem; ubah manual hanya untuk koreksi.'),
            Toggle::make('needs_review')->label('Butuh Review'),
            Toggle::make('notify_creator_on_review')->label('Kabari pembuat saat masuk Review'),
            Toggle::make('notify_creator_on_complete')->label('Kabari pembuat saat selesai'),
        ])->columns(2);
    }

    private static function userSelect(string $name, string $label): Select
    {
        return Select::make($name)->label($label)->searchable()
            ->getSearchResultsUsing(fn (string $search) => User::query()->searchNameOrNip($search)->limit(50)->get()->pluck('name', 'id')->all())
            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name);
    }
}
