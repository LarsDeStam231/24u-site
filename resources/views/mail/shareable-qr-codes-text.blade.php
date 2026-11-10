Jouw QR-codes voor 24 Uur Live

Hallo{{ $name ? ' '.$name : '' }},

Welkom! Je bent voor het eerst ingelogd. Met de twee QR-codes in de bijlagen kun je DCTerra 24 Uur Live en Wensstichting Drenthe steunen.

Website 24 Uur Live:
{{ $codes['website']['url'] }}

Doneren aan Wensstichting Drenthe:
{{ $codes['foundationDonation']['url'] }}

@if ($discordCode)
Discord-code (stuur deze in een privébericht naar de bot op onze Discord-server):
{{ $discordCode }}
Deze code is persoonlijk: deel hem niet met anderen.

@endif
Je mag deze QR-codes opslaan, printen en delen. Ze geven geen toegang tot jouw account.

Groeten,
DCTerra 24 Uur Live
