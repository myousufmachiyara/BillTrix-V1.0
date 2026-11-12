<?php

namespace App\Console\Commands;

use App\Services\BusinessProvisioner;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateBusiness extends Command
{
    protected $signature = 'billtrix:create-business';

    protected $description = 'Create a new business with its owner account, chart of accounts and defaults';

    public function handle(BusinessProvisioner $provisioner): int
    {
        $name = text('Business name', required: true);
        $category = select('Business type', config('billtrix.categories'), default: 'retail');
        $owner = text('Owner name', required: true);
        $email = text('Owner email (login)', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');
        $username = text('Username (optional, e.g. admin)', validate: fn ($v) => match (true) {
            $v === '' => null,
            ! preg_match('/^[A-Za-z0-9_-]{3,50}$/', $v) => '3–50 letters, numbers, - or _.',
            \App\Models\User::withoutGlobalScopes()->where('username', $v)->exists() => 'That username is taken.',
            default => null,
        });
        $pass = password('Password (min 8)', required: true, validate: fn ($v) => strlen($v) >= 8 ? null : 'At least 8 characters.');

        $user = $provisioner->create(['name' => $name, 'category' => $category],
            ['name' => $owner, 'email' => $email, 'username' => $username ?: null, 'password' => $pass]);
        $this->info("Business #{$user->business_id} created. Sign in with ".($username ?: $email).'.');

        return self::SUCCESS;
    }
}
