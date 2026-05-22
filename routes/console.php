<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('product:token {email=owner@example.com} {name=local-product-api}', function () {
    $email = (string) $this->argument('email');
    $tokenName = (string) $this->argument('name');

    /** @var User|null $user */
    $user = User::query()->where('email', $email)->first();

    if (! $user) {
        $this->error("User not found for email: {$email}");

        return self::FAILURE;
    }

    if ($user->tenant_id === null) {
        $this->error('Authenticated user is not linked to a tenant/store.');

        return self::FAILURE;
    }

    if ($user->status !== 'active') {
        $this->error('Inactive users cannot create tokens for product API testing.');

        return self::FAILURE;
    }

    $plainTextToken = $user->createToken($tokenName)->plainTextToken;

    $this->info("Bearer {$plainTextToken}");

    return self::SUCCESS;
})->purpose('Generate a Sanctum bearer token for product API testing.');
