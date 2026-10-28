<?php

namespace App\Console\Commands;

use App\Models\User;
use App\QrCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InviteQrAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qr:invite {email? : E-mailadres van een bestaand account}
        {--students : Nodig alle reeds goedgekeurde studenten uit}
        {--approve : Keur het opgegeven bestaande account eerst goed}
        {--role=student : Rol bij expliciete goedkeuring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verstuur activatielinks voor QR-codes aan bestaande accounts';

    /**
     * Execute the console command.
     */
    public function handle(QrCredentials $credentials): int
    {
        $email = $this->argument('email');
        if ((! $email && ! $this->option('students')) || ($email && $this->option('students'))
            || ($this->option('students') && $this->option('approve'))
            || ! in_array($this->option('role'), User::ROLES, true)) {
            $this->error('Geef één bestaand e-mailadres op, of gebruik --students voor goedgekeurde studenten.');

            return self::FAILURE;
        }

        if ($email) {
            $user = User::query()->where('email', Str::lower($email))->first();
            if (! $user) {
                $this->error('Account niet gevonden. Dien eerst een registratieaanvraag in via de inlogpagina.');

                return self::FAILURE;
            }
            if ($this->option('approve')) {
                $user->role = $this->option('role');
                $user->registration_status = 'approved';
                $user->save();
            }
            if (! $user->isApproved()) {
                $this->error('Keur het account eerst goed, of gebruik bewust --approve --role=student.');

                return self::FAILURE;
            }
            $credentials->invite($user);
            $this->info('Activatiemail verstuurd. Bestaande QR-codes zijn niet gewijzigd.');

            return self::SUCCESS;
        }

        $count = 0;
        User::query()->where('role', 'student')->where('registration_status', 'approved')
            ->eachById(function (User $user) use ($credentials, &$count): void {
                $credentials->invite($user);
                $count++;
            });
        $this->info($count.' activatiemails verstuurd.');

        return self::SUCCESS;
    }
}
