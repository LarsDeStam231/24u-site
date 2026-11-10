<?php

use App\ConfigurePasskeyCeremony;
use App\Models\Passkey;
use App\Models\User;

return [
    'actions' => ['configure_ceremony_step_manager_factory' => ConfigurePasskeyCeremony::class],
    'relying_party' => [
        'name' => config('app.name'),
        'id' => parse_url(config('app.url'), PHP_URL_HOST),
        'icon' => null,
    ],
    'models' => [
        'passkey' => Passkey::class,
        'authenticatable' => User::class,
    ],
];
