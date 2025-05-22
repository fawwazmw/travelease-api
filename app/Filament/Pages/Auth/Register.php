<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Register as BaseRegister;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth; // Pastikan ini ada
use Illuminate\Auth\Events\Registered;
use App\Models\User;

class Register extends BaseRegister
{
    // protected static string $view = 'filament.pages.auth.register'; // Dihapus agar menggunakan view default

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ])
            ->statePath('data');
    }

    /**
     * Mendapatkan komponen form untuk nama pengguna.
     */
    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('filament-panels::pages/auth/register.form.name.label'))
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::pages/auth/register.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(User::class);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/register.form.password.label'))
            ->password()
            ->required()
            ->rule(\Illuminate\Validation\Rules\Password::default())
            ->dehydrateStateUsing(static fn (string $state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
            ->password()
            ->required()
            ->dehydrated(false);
    }

    public function register(): ?\Filament\Http\Responses\Auth\Contracts\RegistrationResponse
    {
        try {
            // $this->rateLimit(5); // Contoh jika ada method rateLimit
        } catch (\Illuminate\Http\Exceptions\ThrottleRequestsException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.email' => __('filament-panels::pages/auth/register.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]),
            ]);
        }

        $data = $this->form->getState();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'visitor',
        ]);

        event(new Registered($user));

        Auth::login($user); // Cukup satu kali pemanggilan

        return app(\Filament\Http\Responses\Auth\Contracts\RegistrationResponse::class);
    }
}
