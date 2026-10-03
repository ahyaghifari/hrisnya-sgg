<?php

namespace App\Filament\Persuratan\Resources\Penggunas\Pages;

use App\Filament\Persuratan\Resources\Penggunas\PenggunaResource;
use App\Models\User as HrisUser;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ManagePenggunas extends ManageRecords
{
    protected static string $resource = PenggunaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pengguna')->modalWidth('2xl')
                ->mutateDataUsing(function (array $data): array {
                    $sumber = $data['sumber'] ?? 'manual';
                    unset($data['sumber']);

                    if ($sumber !== 'hris') {
                        return $data;
                    }

                    // Dari HRIS: nama & username (NIP) diambil dari HRIS, langsung jadi. Password acak (belum bisa dipakai
                    // login sampai ada mekanisme login untuk akun HRIS / diatur manual lewat Edit).
                    $u = HrisUser::findOrFail($data['hris_user_id']);

                    return [
                        'nama_lengkap' => $u->name,
                        'username' => PenggunaResource::usernameDariHris($u),
                        'password' => Hash::make(Str::random(40)),
                        'status' => $data['status'] ?? 'aktif',
                        'hris_user_id' => $u->getKey(),
                    ];
                }),
        ];
    }
}
