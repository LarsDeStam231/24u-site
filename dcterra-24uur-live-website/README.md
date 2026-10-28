# DCTerra 24 Uur Live 2026 — websiteprototype

**Status: werkend prototype, nog NIET gepubliceerd en niet verbonden met een Mollie-account.**

Dit project zet het goedgekeurde rood-zwart-witte ontwerp om naar een echte, responsive website. Het bevat de **door de gebruiker aangeleverde afbeelding van het officiële Wensstichting Drenthe-logo**, sponsorwerving, livestream, donatieformulier, challenge-mijlpalen, programma, crew-dashboard en transparante OBS-overlay.


## DCTerra-logo

De navigatie en de footer gebruiken nu het **DCTerra-beeldmerk (D·C / TERRA)** en niet langer gewone tekst. Voor de betrouwbare weergave op alle pagina's en in de offline demo wordt de lokaal meegeleverde, visueel nagebouwde `public/assets/dcterra-logo.svg` gebruikt. Voor definitieve publicatie is het raadzaam het originele SVG/PNG-bestand van de communicatieafdeling lokaal te plaatsen zodat het logo volledig merkconform en onafhankelijk van de externe website werkt. Het Wensstichting Drenthe-logo blijft het door de gebruiker aangeleverde origineel.

## 1. Eerst bekijken zonder installatie

Dubbelklik op **`INTERACTIEVE_DEMO.html`**. Dit is een volledig zelfstandige demonstratie van de website (ook op Windows). Je kunt door de pagina's navigeren en het donatiemenu bekijken. In de demo staat duidelijk dat **€ 3.475 een voorbeeldbedrag** is. De demo verwerkt geen betalingen of sponsoraanvragen.

Voorbeelden als afbeeldingen: `home-desktop-preview.png`, `home-mobile-preview.png`, `sponsoren-desktop-preview.png` en `obs-overlay-preview.png`.

## 2. Echte lokale website starten (Windows)

1. Installeer **Node.js 22.13 of nieuwer** via <https://nodejs.org/>. (Geen npm-installatie nodig; er zijn geen externe afhankelijkheden.)
2. Dubbelklik op **`START_WINDOWS.bat`**.
3. Je browser opent `http://localhost:3000`.
4. Aanpassingen werken direct na vernieuwen van de pagina; stop de server met `Ctrl+C`.

Voor Mac / Linux: `cp .env.example .env && node server.mjs`.

### URLs

| Pagina | Adres |
|---|---|
| Homepage | `http://localhost:3000/` |
| Livestream | `/livestream` |
| Doneren | `/doneren` |
| Challenges | `/challenges` |
| Programma | `/programma` |
| Sponsoren | `/sponsoren` |
| Over het goede doel | `/over-ons` |
| Crewbeheer | `/crew` |
| OBS-transparante teller | `/overlay` |
| Compacte OBS-teller | `/overlay?style=compact` |
| Demonstratie met fictieve tussenstand | `/?preview=1` |
| Transparante demo-overlay | `/overlay?preview=1` |

## 3. Afgeschermd dashboard activeren

Open `.env` (wordt bij de eerste start aangemaakt uit `.env.example`) en vul bijvoorbeeld in:

```dotenv
PORT=3000
APP_URL=http://localhost:3000
ADMIN_PASSWORD=GEBRUIK_HIER_EEN_UNIEK_LANG_WACHTWOORD
PAYMENTS_ENABLED=false
MOLLIE_API_KEY=
DB_PATH=./data/site.sqlite
```

Herstart de server en log in op `/crew`. Daar kun je:

- De livestream handmatig op **LIVE** zetten en het **YouTube video-ID** invullen;
- Het doelbedrag aanpassen;
- Extern bevestigde sponsorbedragen of contante giften toevoegen of corrigeren, met toelichting en auditlog;
- Sponsoraanvragen bekijken en verwijderen;
- Alleen bevestigde sponsoren publiceren of verwijderen.

**Let op:** het formulier verstuurt nog geen e-mails. Aanvragen worden opgeslagen in de SQLite-database en zijn alleen zichtbaar in het crew-dashboard. Gebruik de sponsorfunctie pas publiek nadat privacy, toegang, hosting en verwerking zijn goedgekeurd.

## 4. Mollie koppelen na goedkeuring

**Eerst afstemmen met Wensstichting Drenthe:** wie is rekeninghouder, welk Mollie-profiel krijgt de betalingen, wie draagt transactiekosten en wie mag technische API-toegang krijgen? De site mag niet claimen dat DCTerra geld voor de stichting beheert als dit niet contractueel is geregeld.

Na akkoord:

1. Maak een **test-API-sleutel** aan in het goedgekeurde Mollie-account.
2. Zet een publiek bereikbare HTTPS-testomgeving op. De Mollie-webhook is vanaf `localhost` niet bereikbaar, dus test lokaal met een beveiligde tunnel of een testserver.
3. Vul `.env` in met `APP_URL=https://jouw-testdomein.nl`, `PAYMENTS_ENABLED=true` en `MOLLIE_API_KEY=test_...`.
4. Maak enkele Mollie-testbetalingen en test vooral bevestiging, annulering, vertraging, herhaalde webhooks en terugbetalingen.
5. Neem **pas na gezamenlijke goedkeuring en technische/securityreview** een `live_...`-sleutel in gebruik op een HTTPS-productieomgeving.

De implementatie doet het volgende:

- `POST /api/donations/create` maakt een betaalopdracht aan met campagnemetadata en stuurt de donateur naar Mollie's eigen betaalpagina;
- `POST /api/mollie/webhook` haalt de status **zelf opnieuw bij Mollie** op en telt dus geen onbevestigde meldingen;
- Betalingen zijn idempotent: herhaalde webhooks tellen niet dubbel;
- Een terugbetaling verlaagt het netto getelde donatiebedrag;
- In de publieke teller staan geen persoonsgegevens van donateurs.

**De teller toont betaalde brutodonaties vóór transactiekosten, minus geregistreerde terugbetalingen, plus handmatig bevestigde bijdragen.** Donaties via de externe Wensstichting-website worden niet vanzelf ingelezen.

### Noodoplossing wanneer Mollie niet actief is

Het formulier biedt een duidelijke knop naar <https://wensstichtingdrenthe.nl/doneren> en legt uit dat die betalingen niet vanzelf in de DCTerra-teller komen. De crew kan een door de stichting bevestigde opbrengst later handmatig toevoegen.

## 5. OBS-instellingen

Voeg in OBS een **Browserbron** toe met de URL `https://jouw-domein.nl/overlay`.

- Normaal: breedte circa **820**, hoogte **200** pixels;
- Compact: URL `/overlay?style=compact`, breedte circa **620**, hoogte **150** pixels;
- Achtergrond is **transparant**;
- De teller ververst elke **5 seconden**;
- `?preview=1` toont alleen een gemarkeerd voorbeeld met € 3.475, nooit gebruiken als echte streamopbrengst.

## 6. YouTube en overige sociale media

Gebruikte, eerder opgegeven kanalen:

- YouTube: <https://www.youtube.com/@DCterra24uur>
- Instagram: <https://www.instagram.com/dcterra24uurlive/>
- Twitch: <https://www.twitch.tv/dcterra24uur>

Vul in crewbeheer het **11-tekens video-ID** van de geplande YouTube-stream in om de ingesloten video zichtbaar te maken. Zonder ID toont de site een nette aankondiging en een werkende kanaallink.

## 7. Techniek en hosting

- Frontend: normale HTML, CSS en JavaScript, zonder framework of externe scripts;
- Server: Node.js 22 `http` en ingebouwde SQLite (`node:sqlite`);
- Data: `data/site.sqlite` met betalingen, sponsoren, aanvragen, mutaties en adminsessies;
- Veiligheidsmaatregelen: server-side API-sleutels, HTTPS-voorwaarde voor live-betalingen, origin-controle, SameSite/HttpOnly-sessies, beperkte aanmeldpogingen, invoervalidatie, escaping en Content Security Policy;
- Herhaalbare tests: `npm test`.

**Voor echte publicatie:** gebruik een server met **permanente opslag** (geen tijdelijke/serverless schijf), HTTPS, versleutelde back-ups, monitoring, beveiligingsupdates en afgesproken databeheer. De ingebouwde SQLite van Node.js 22 is nog experimenteel. Een productieteam kan eventueel migreren naar een ondersteunde PostgreSQL-database. Laat Software Development de productie-inrichting beoordelen.

## 8. Nog af te stemmen vóór 16 december

- [ ] Goedkeuring van Wensstichting Drenthe voor logo, tekst, betalingen en vermelding;
- [ ] Een Mollie-account bij de overeengekomen ontvanger + test/live-sleutels;
- [ ] Definitief webadres, hosting, HTTPS, database-back-ups en verantwoordelijke beheerders;
- [ ] AVG/privacyverklaring, bewaartermijnen en toestemming/contactprocedure voor sponsoraanvragen;
- [ ] Werkelijke sponsoren, hun logo's en afgesproken sponsortegenprestaties;
- [ ] Definitief programma, challenges en presentatorenrooster;
- [ ] Het definitieve YouTube video-ID en volledige OBS-test;
- [ ] Productiereview van betalingen, toegang, rate limits, incidenten en refunds;
- [ ] Verwijder het `noindex`-metatag uit `public/index.html` als de site openbaar geïndexeerd mag worden.

## 9. Betrouwbare bronnen gebruikt tijdens de bouw

- Stichting: <https://wensstichtingdrenthe.nl/> en <https://wensstichtingdrenthe.nl/doneren>
- DCTerra locatie Van Schaikweg 98: <https://mbo.dcterra.nl/over-dcterra/regios/emmen/van-schaikweg-98/>
- Mollie create payment: <https://docs.mollie.com/reference/create-payment>
- Mollie webhooks: <https://docs.mollie.com/reference/webhooks>
- Mollie payment links: <https://docs.mollie.com/docs/payment-links>

De studioachtergrond is een uitsnede/bewerking uit de eerder goedgekeurde conceptvisual. Het Wensstichting-logo is de door de gebruiker aangeleverde afbeelding. Er zijn **geen fictieve sponsoren** in de werkende website geplaatst.
