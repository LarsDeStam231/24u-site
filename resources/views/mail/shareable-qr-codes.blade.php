<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Jouw QR-codes — DCTerra 24 Uur Live</title>
</head>
<body>
    <h1>Jouw QR-codes voor 24 Uur Live</h1>
    <p>Hallo{{ $name ? ' '.$name : '' }},</p>
    <p>Welkom! Je bent voor het eerst ingelogd. Hieronder vind je de twee QR-codes die je kunt delen om DCTerra 24 Uur Live en Wensstichting Drenthe te steunen.</p>

    <h2>Website 24 Uur Live</h2>
    <p>Scan deze QR-code om onze website te bezoeken.</p>
    <p><img src="{{ $message->embedData($codes['website']['image'], 'website-qr.png', 'image/png') }}" alt="QR-code voor de website van 24 Uur Live" width="264" height="264"></p>
    <p><a href="{{ $codes['website']['url'] }}">Bezoek de website van 24 Uur Live</a></p>

    <h2>Wensstichting Drenthe</h2>
    <p>Scan deze QR-code om rechtstreeks te doneren aan Wensstichting Drenthe.</p>
    <p><img src="{{ $message->embedData($codes['foundationDonation']['image'], 'wensstichting-qr.png', 'image/png') }}" alt="QR-code om te doneren aan Wensstichting Drenthe" width="264" height="264"></p>
    <p><a href="{{ $codes['foundationDonation']['url'] }}">Doneer aan Wensstichting Drenthe</a></p>

    <p>Je vindt beide QR-codes ook als PNG-bijlage bij deze mail. Je mag ze opslaan, printen en delen. Deze QR-codes geven geen toegang tot jouw account.</p>
    <p>Groeten,<br>DCTerra 24 Uur Live</p>
</body>
</html>
