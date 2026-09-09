<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile;

/**
 * Club panel profile page (user menu → Profile). Lets a club user change
 * their display name and — in a dedicated Security section — their
 * password, which requires re-entering the current password. Login email
 * is shown but not editable (Macron manages it).
 */
class Profile extends EditProfile
{
    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profile')
                ->schema([
                    $this->getNameFormComponent(),
                    TextInput::make('email')
                        ->label('Email')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Contact Macron if your login email needs to change.'),
                ]),

            Section::make('Security')
                ->description('Leave the password fields blank unless you want to change your password.')
                ->schema([
                    TextInput::make('current_password')
                        ->label('Current password')
                        ->password()
                        ->revealable(filament()->arePasswordsRevealable())
                        ->autocomplete('current-password')
                        ->currentPassword(fn (Get $get): bool => filled($get('password')))
                        ->required(fn (Get $get): bool => filled($get('password')))
                        ->dehydrated(false),
                    $this->getPasswordFormComponent()
                        ->label('New password'),
                    $this->getPasswordConfirmationFormComponent()
                        ->label('Confirm new password'),
                ]),
        ]);
    }
}
