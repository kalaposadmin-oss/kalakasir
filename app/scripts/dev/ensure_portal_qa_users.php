<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = App\Models\Tenant::query()->first();
if (! $tenant) {
    throw new RuntimeException('Tenant not found');
}

$roleSetup = app(App\Services\TenantDefaultRoleSetupService::class);

$ensureUser = static function (string $email, string $name, string $roleName) use ($tenant, $roleSetup): array {
    $user = App\Models\User::query()->firstOrNew(['email' => $email]);
    $user->tenant_id = $tenant->id;
    $user->name = $name;
    $user->password = bcrypt('secret123');
    $user->save();

    $roleSetup->assignNamedRole((string) $tenant->id, (string) $user->id, $roleName);

    return [
        'email' => $user->email,
        'role' => $roleName,
    ];
};

$result = [
    'tenant_id' => $tenant->id,
    'users' => [
        $ensureUser('qa.owner.portal@kalakasir.local', 'QA Owner Portal', 'Owner'),
        $ensureUser('qa.cashier.portal@kalakasir.local', 'QA Cashier Portal', 'Cashier'),
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;