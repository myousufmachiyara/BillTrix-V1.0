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
        $pass = password('Password (min 8)', required: true, validate: fn ($v) => strlen($v) >= 8 ? null : 'At least 8 characters.');

        $user = $provisioner->create(['name' => $name, 'category' => $category], ['name' => $owner, 'email' => $email, 'password' => $pass]);
        $this->info("Business #{$user->business_id} created. {$email} can now sign in.");

        return self::SUCCESS;
    }
}
