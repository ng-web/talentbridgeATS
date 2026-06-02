<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

final class CreateAdminUser extends Command
{
    protected $signature   = 'app:create-admin';
    protected $description = 'Create the initial admin user for this installation';

    public function handle(): int
    {
        $this->info('Creating admin user...');

        $name = $this->ask('Full name');
        $email = $this->ask('Email address');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");
            return self::FAILURE;
        }

        $password = $this->secret('Password (min 12 characters)');
        $confirm  = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)]]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));
            return self::FAILURE;
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $user->assignRole('admin');

        $this->info("Admin user [{$email}] created successfully.");
        $this->line('You can now log in at /login');

        return self::SUCCESS;
    }
}
