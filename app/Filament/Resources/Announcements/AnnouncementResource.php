<?php

namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\BaseResource;
use App\Models\Announcement;
use App\Models\Master\JobPositions;
use App\Notifications\AnnouncementPublished;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnnouncementResource extends BaseResource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $modelLabel = 'Pengumuman';

    // Tepat di bawah Dashboard (Dashboard = -2), di atas grup navigasi lain, tanpa grup.
    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::canBrowseAllCompanies() ? $query : $query->where('company_id', static::companyId());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 2])->columnSpanFull()->schema([
                    Group::make([
                        TextInput::make('title')->label('Judul')->required()->maxLength(255),
                        Textarea::make('summary')->label('Deskripsi Singkat (tampil di notifikasi)')->required()->rows(2)->maxLength(200),
                        \Filament\Forms\Components\Select::make('tone')->label('Warna Banner Beranda')->options(['info' => 'Info (biru)', 'success' => 'Sukses (hijau)', 'warning' => 'Peringatan (kuning)'])->default('info')->required()->native(false),
                        \Filament\Forms\Components\Select::make('delivery')->label('Cara Tampil')->options(Announcement::DELIVERY)->default('both')->required()->native(false)->live()
                            ->helperText('"Notifikasi saja" tidak muncul di Beranda.'),
                        \Filament\Forms\Components\DatePicker::make('banner_until')->label('Banner Tampil Sampai')->native(false)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('delivery') !== 'notification')
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('delivery') !== 'notification')
                            ->helperText('Banner hilang setelah tanggal ini.'),
                        RichEditor::make('body')->label('Isi Pengumuman')->required()->extraInputAttributes(['style' => 'min-height: 16rem;']),
                    ]),
                    // Pohon target: centang perusahaan/departemen/divisi = semua jabatan di bawahnya
                    // ikut, lalu bisa uncentang jabatan tertentu. State = id jabatan akhir
                    // (tidak ke kolom, disimpan lewat syncTargets()).
                    ViewField::make('job_position_ids')
                        ->label('Penerima')
                        ->view('filament.forms.announcement-targets')
                        ->viewData(fn () => static::targetTree())
                        ->default([])
                        // Penerima dikunci setelah terbit (notifikasi sudah terkirim ke mereka); isi pengumuman tetap bisa diubah.
                        ->disabled(fn (?Announcement $record) => $record?->isPublished())
                        ->dehydrated(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->limit(60),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'published' ? 'Terbit' : 'Draft')
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('delivery')->label('Tampil via')->badge()->formatStateUsing(fn (string $state) => Announcement::DELIVERY[$state] ?? $state),
                TextColumn::make('job_positions_count')->label('Jabatan')->counts('jobPositions'),
                TextColumn::make('publisher.name')->label('Oleh')->placeholder('-'),
                TextColumn::make('published_at')->label('Terbit')->dateTime('j M Y H:i')->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make(),
                static::publishAction(),
                DeleteAction::make()->visible(fn (Announcement $record) => ! $record->isPublished()),
            ]);
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Terbitkan')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(fn (Announcement $record) => ! $record->isPublished())
            ->requiresConfirmation()
            ->modalDescription(fn (Announcement $record) => ($record->sendsNotification() ? 'Notifikasi akan dikirim ke ' : 'Banner akan tampil untuk ') . $record->recipients()->count() . ' karyawan aktif. Tidak bisa ditarik kembali.')
            ->action(fn (Announcement $record) => static::publish($record));
    }

    public static function publish(Announcement $announcement): void
    {
        if ($announcement->isPublished()) {
            return;
        }

        $recipients = $announcement->recipients()->get();

        if ($recipients->isEmpty()) {
            Notification::make()->title('Belum ada karyawan penerima, pilih jabatan dulu')->danger()->send();

            return;
        }

        $announcement->update(['status' => 'published', 'published_at' => now(), 'published_by' => auth()->id()]);
        $announcement->load('publisher');

        if ($announcement->sendsNotification()) {
            \Illuminate\Support\Facades\Notification::send($recipients, new AnnouncementPublished($announcement));
        }

        Notification::make()->title("Pengumuman terbit ke {$recipients->count()} karyawan")->success()->send();
    }

    /** Simpan target hasil centang pohon - dibatasi ke jabatan di perusahaan yang boleh dilihat admin ini. */
    public static function syncTargets(Announcement $announcement, array $ids): void
    {
        $allowed = JobPositions::query()
            ->whereIn('id', $ids)
            ->when(! static::canBrowseAllCompanies(), fn ($q) => $q->where('company_id', static::companyId()))
            ->pluck('id');

        DB::transaction(fn () => $announcement->jobPositions()->sync($allowed));
    }

    public static function targetTree(): array
    {
        return Announcement::targetTree(static::canBrowseAllCompanies() ? null : static::companyId());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
