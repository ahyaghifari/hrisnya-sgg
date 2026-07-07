<?php

namespace App\Filament\Candidate\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (request()->filled('job_id')) {
            session(['after_login_job_id' => request()->query('job_id')]);
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Credentials are incorrect.',
            'data.password' => 'Credentials are incorrect.',
        ]);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return parent::hasFullWidthFormActions();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
            $this->registerAction(),
        ];
    }

    public function registerAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('register')
            ->label('Sign Up')
            ->button()
            ->outlined()
            ->color('white')
            ->url(filament()->getRegistrationUrl());
    }
}
