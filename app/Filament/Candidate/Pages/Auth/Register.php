<?php

namespace App\Filament\Candidate\Pages\Auth;

use App\Models\Candidate\Candidates;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(Candidates::class, 'email')
                    ->maxLength(255),

                TextInput::make('phone_number')
                    ->label('Nomor Telepon')
                    ->tel()
                    ->required()
                    ->maxLength(20),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->same('passwordConfirmation')
                    ->revealable(),

                TextInput::make('passwordConfirmation')
                    ->label('Konfirmasi Password')
                    ->password()
                    ->required()
                    ->dehydrated(false)
                    ->revealable(),
            ])
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Candidates
    {
        $candidate = Candidates::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['password']),
            'overall_status' => 'new',
        ]);

        $this->redirect('filament.candidate.auth.login');
        return $candidate;
    }

    public function redirect($url, $navigate = false)
    {
        return parent::redirect($url, $navigate);
    }
}
