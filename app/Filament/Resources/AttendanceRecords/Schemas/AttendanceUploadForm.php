<?php

namespace App\Filament\Resources\AttendanceRecords\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceUploadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Upload Data Absensi')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('File Excel')
                            ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('local')
                            ->directory('uploads/attendance')
                            ->helperText('Unggah file Excel (.xlsx, .xls) atau CSV berisi data absensi. Maksimal ukuran file 10MB.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}