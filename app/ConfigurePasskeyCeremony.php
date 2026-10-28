<?php

namespace App;

use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

class ConfigurePasskeyCeremony extends ConfigureCeremonyStepManagerFactoryAction
{
    public function execute(): CeremonyStepManagerFactory
    {
        $factory = parent::execute();
        $factory->setAllowedOrigins([rtrim(config('app.url'), '/')], false);

        return $factory;
    }
}
