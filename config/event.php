<?php

return [
    'event' => [
        'name' => 'DCTerra 24 Uur Live',
        'year' => 2026,
        'title' => ['24 UUR SAMEN.', '1 GOED DOEL.'],
        'introduction' => 'Studenten, docenten en sponsoren komen samen voor Wensstichting Drenthe. 24 uur lang gaming, challenges en entertainment — allemaal voor een goed doel.',
        'startsAt' => '2026-12-16T10:00:00+01:00',
        'endsAt' => '2026-12-17T10:00:00+01:00',
        'location' => ['name' => 'DCTerra', 'street' => 'Van Schaikweg 98', 'city' => 'Emmen', 'postcode' => '7811 KL'],
        'email' => '24uurlive@dcterra.nl',
        'images' => [
            'brand' => 'assets/event/dcterra-logo.svg',
            'hero' => 'assets/event/studio-hero.jpg',
            'livestream' => 'assets/event/studio-live.jpg',
            'foundation' => 'assets/event/wensstichting-drenthe-officieel.png',
        ],
        'foundation' => [
            'name' => 'Wensstichting Drenthe',
            'url' => 'https://wensstichtingdrenthe.nl/',
            'donateUrl' => 'https://wensstichtingdrenthe.nl/doneren',
            'description' => 'Samen wensen mogelijk maken voor kinderen en gezinnen in Drenthe.',
        ],
        'fundraising' => ['raisedCents' => 347500, 'goalCents' => 1000000, 'isPreview' => true],
        'stream' => ['live' => false, 'url' => 'https://www.youtube.com/@DCterra24uur'],
        'donationAmounts' => [5, 10, 25, 50],
        'highlights' => [
            ['icon' => 'game', 'title' => '24 UUR LIVE', 'text' => 'Non-stop gaming en entertainment'],
            ['icon' => 'heart', 'title' => 'VOOR WENSSTICHTING DRENTHE', 'text' => 'Samen wensen mogelijk maken!'],
            ['icon' => 'users', 'title' => 'STUDENTEN & DOCENTEN', 'text' => 'De hele school doet mee'],
            ['icon' => 'trophy', 'title' => 'CHALLENGES', 'text' => 'Bij elke mijlpaal iets bijzonders'],
            ['icon' => 'building', 'title' => 'SPONSOREN', 'text' => 'Maak dit evenement mogelijk'],
        ],
        'challenges' => [
            ['amountCents' => 10000, 'name' => 'Rad van challenges', 'description' => 'Het rad bepaalt de volgende uitdaging.'],
            ['amountCents' => 25000, 'name' => 'Docent game-challenge', 'description' => 'Docenten nemen de controller over.'],
            ['amountCents' => 50000, 'name' => 'Mystery challenge', 'description' => 'Een bijzondere uitdaging wordt onthuld.'],
            ['amountCents' => 100000, 'name' => 'Speciale challenge', 'description' => 'Een speciale actie voor het goede doel.'],
            ['amountCents' => 250000, 'name' => 'Grote nachtchallenge', 'description' => 'Een extra uitdaging tijdens de nacht.'],
            ['amountCents' => 500000, 'name' => 'Hoofdchallenge', 'description' => 'Een grote uitdaging wordt ontgrendeld.'],
            ['amountCents' => 750000, 'name' => 'Verrassing', 'description' => 'Een bijzondere verrassing tijdens de stream.'],
            ['amountCents' => 1000000, 'name' => 'Sandmann Challenge', 'description' => 'Meneer Sandmann gaat kaal!'],
        ],
        'schedule' => [
            ['id' => 'opening', 'date' => '2026-12-16', 'time' => '10:00', 'title' => 'Officiële aftrap', 'description' => 'Opening door Wensstichting Drenthe'],
            ['id' => 'gaming', 'date' => '2026-12-16', 'time' => 'Overdag', 'title' => 'Gaming en activiteiten', 'description' => 'Studenten, docenten en gastopleidingen'],
            ['id' => 'evening', 'date' => '2026-12-16', 'time' => 'Avond', 'title' => 'Avondprogramma', 'description' => 'Challenges en presentaties'],
            ['id' => 'night', 'date' => '2026-12-17', 'time' => 'Nacht', 'title' => 'Nachtstream', 'description' => 'Gaming en nachtchallenges'],
            ['id' => 'finale', 'date' => '2026-12-17', 'time' => '10:00', 'title' => 'Finale en afsluiting', 'description' => 'Eindstand en afsluiting'],
        ],
        'sponsorTiers' => [
            ['id' => 'gold', 'name' => 'Goud', 'price' => 'Meer dan € 100', 'sponsors' => []],
            ['id' => 'silver', 'name' => 'Zilver', 'price' => '€ 50 tot € 100', 'sponsors' => []],
            ['id' => 'bronze', 'name' => 'Brons', 'price' => 'Tot € 50', 'sponsors' => []],
        ],
        'sponsorBenefits' => ['Zichtbaarheid tijdens de livestream', 'Vermelding op onze website', 'Samen steun je een goed doel'],
    ],
    'socials' => [
        ['label' => 'YouTube', 'icon' => 'youtube', 'href' => 'https://www.youtube.com/@DCterra24uur'],
        ['label' => 'Instagram', 'icon' => 'instagram', 'href' => 'https://www.instagram.com/dcterra24uurlive/'],
        ['label' => 'Twitch', 'icon' => 'twitch', 'href' => 'https://www.twitch.tv/dcterra24uur'],
    ],
    'pages' => [
        'home' => [
            'route' => 'home', 'label' => 'Home', 'heading' => '24 uur samen. 1 goed doel.',
            'description' => 'DCTerra 24 Uur Live: samen gamen, challenges aangaan en wensen mogelijk maken voor Wensstichting Drenthe.',
        ],
        'livestream' => [
            'route' => 'livestream.index', 'label' => 'Livestream', 'heading' => 'Kijk mee. Doe mee.',
            'description' => 'Volg DCTerra 24 Uur Live via YouTube. Een etmaal vol gaming, challenges en entertainment voor het goede doel.',
        ],
        'donations' => [
            'route' => 'donations.index', 'label' => 'Doneren', 'heading' => 'Help wensen mogelijk maken.',
            'description' => 'Steun Wensstichting Drenthe. Kies je donatiebedrag en help ons samen het doel te bereiken.',
        ],
        'challenges' => [
            'route' => 'challenges.index', 'label' => 'Challenges', 'heading' => 'Hoe meer we ophalen, hoe meer we doen!',
            'description' => 'Ontdek alle challenge-mijlpalen. Elke bijdrage brengt ons dichter bij de volgende uitdaging tijdens de livestream.',
        ],
        'program' => [
            'route' => 'program.index', 'label' => 'Programma', 'heading' => '24 uur op het programma.',
            'description' => 'Van de officiële aftrap tot de finale: bekijk de voorlopige planning van DCTerra 24 Uur Live.',
        ],
        'sponsors' => [
            'route' => 'sponsors.index', 'label' => 'Sponsoren', 'heading' => 'Samen maken we dit mogelijk.',
            'description' => 'Bedrijven, organisaties en ondernemers steunen DCTerra 24 Uur Live en helpen Wensstichting Drenthe.',
        ],
        'about' => [
            'route' => 'about.index', 'label' => 'Over ons', 'heading' => '24 uur. 1 goed doel. Samen sterker.',
            'description' => 'Studenten, docenten en sponsoren zetten zich samen in voor Wensstichting Drenthe. Lees meer over het evenement en het goede doel.',
        ],
    ],
];
