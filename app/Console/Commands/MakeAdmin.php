<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'tfc:admin {email} {name=TFC Administrator}';

    protected $description = 'Create (or reset) a TFC administrator with a new random password, printed once. Gets full access everywhere, including Kilimo.';

    public function handle(): int
    {
        $password = Str::password(16, symbols: false);
        $user = User::query()->firstOrNew(['email' => strtolower($this->argument('email'))]);
        $user->forceFill([
            'name' => $user->name ?: $this->argument('name'),
            'password' => $password,
            'is_admin' => true,
            'is_active' => true,
            'role' => 'admin',
        ])->save();

        $this->line("Email: {$user->email}");
        $this->line("Password: {$password}");

        return self::SUCCESS;
    }
}
