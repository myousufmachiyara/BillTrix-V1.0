<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;

/** Give a user a username (and optionally a new password) from the server. */
class SetLogin extends Command
{
    protected $signature = 'billtrix:set-login
        {username : Username to sign in with, e.g. admin}
        {--user= : Email or id of the user (asks when left out)}
        {--password= : New password (asks when left out; press Enter to keep the current one)}';

    protected $description = 'Set the username and password a user signs in with';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        if (! preg_match('/^[A-Za-z0-9_-]{3,50}$/', $username)) {
            $this->error('Username must be 3–50 letters, numbers, - or _.');

            return self::FAILURE;
        }

        $users = User::withoutGlobalScopes()->orderBy('id');
        $who = $this->option('user');
        $user = $who
            ? (clone $users)->where(is_numeric($who) ? 'id' : 'email', $who)->first()
            : User::withoutGlobalScopes()->find(select('Which user?', (clone $users)->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} <{$u->email}>"])->all()));
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if (User::withoutGlobalScopes()->where('username', $username)->whereKeyNot($user->id)->exists()) {
            $this->error("The username \"{$username}\" is already taken.");

            return self::FAILURE;
        }

        $pass = $this->option('password') ?? password('New password (Enter to keep the current one)');
        if ($pass !== '' && strlen($pass) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user->forceFill(array_filter(['username' => $username, 'is_active' => true, 'password' => $pass !== '' ? $pass : null]))->save();
        $this->info("{$user->name} can now sign in as \"{$username}\".");

        return self::SUCCESS;
    }
}
