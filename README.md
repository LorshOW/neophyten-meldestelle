# Neophyten-Meldestelle

Laravel-Fachanwendung für die Meldestelle invasiver Pflanzen im Erzgebirge.

Bürger:innen melden Fundorte über die öffentliche Web-App (Karte → KoboToolbox).
Jede Meldung läuft per Webhook in diese Anwendung, wird dort zu einem **Vorgang**
und durchläuft einen konfigurierbaren Workflow von Innendienst und Außendienst.

## Überblick

```
Öffentliche Melde-App  →  KoboToolbox  →  Webhook  →  kobo_submissions (Rohdaten)
                                                            ↓
                                                        Vorgang
                                                            ↓
                                    Innendienst → Außendienst → Abschluss
                                                            ↓
                                          E-Mails nach konfigurierbaren Regeln
```

| Bereich | URL | Für wen |
|---|---|---|
| Öffentliche Meldeseite | `/` | alle |
| Verwaltung | `/admin` | Super-Admin, Admin, Innendienst |
| Außendienst (mobil) | `/aussendienst` | Außendienst |
| Kobo-Webhook | `POST /api/webhooks/kobo` | KoboToolbox |

## Technik

* Laravel 12 · PHP 8.2
* Filament 5 (zwei Panels)
* spatie/laravel-permission
* MySQL (MAMP) · Queue über die Datenbank

## Einrichtung

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work
```

Die Anwendung läuft lokal mit `php artisan serve`.

### Datenbank unter MAMP

MAMPs MySQL wird über den Socket angesprochen, weil andere Programme auf diesem
Rechner `127.0.0.1:3306` belegen können und die TCP-Verbindung dann auf einem
fremden Server landet:

```
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

### Demo-Zugänge

`php artisan migrate --seed` legt außerhalb von Produktion vier Konten an
(Passwort jeweils `password`):

| E-Mail | Rolle |
|---|---|
| superadmin@meldestelle.test | Super-Admin |
| admin@meldestelle.test | Administration |
| innendienst@meldestelle.test | Innendienst |
| aussendienst@meldestelle.test | Außendienst |

## KoboToolbox anbinden

1. `.env` ausfüllen:

   ```
   KOBO_ASSET_UID=aUTdQWQfjDBbnYqBtqWWUP
   KOBO_API_TOKEN=<Token aus dem Kobo-Konto>
   KOBO_WEBHOOK_SECRET=<selbst gewähltes Geheimnis>
   ```

2. Im Kobo-Asset unter **Settings → REST Services** einen Dienst anlegen:
   * Endpoint: `https://<domain>/api/webhooks/kobo`
   * Custom HTTP header: `X-Kobo-Secret: <dasselbe Geheimnis>`

3. Die Feldzuordnung in `config/kobo.php` ist gegen das Live-Formular geprüft
   (Stand: September 2026). Nach jeder Änderung am Kobo-Formular erneut abgleichen:

   ```bash
   php artisan kobo:inspect-form
   ```

   Wichtig: Ein Webhook liefert die **Antwortwerte** (`kanadische_goldrute`),
   ein CSV-Export die **Beschriftungen** (`Kanadische Goldrute`). Beide Schreib-
   weisen sind hinterlegt, Mehrfachauswahlen kommen per Webhook als eine durch
   Leerzeichen getrennte Zeichenkette an.

## Befehle

| Befehl | Zweck |
|---|---|
| `kobo:check` | Verbindung und Webhook prüfen – sagt, **warum** nichts ankommt |
| `kobo:inspect-form` | Fragenamen und Beschriftungen des Formulars anzeigen |
| `kobo:sync --process --now` | Meldungen über die API nachholen (Fallback ohne Webhook) |
| `meldestelle:import-stand <datei>` | Stand der alten Monitor-Anwendung importieren |
| `geocode:vorgaenge` | PLZ/Ort/Kreis über Nominatim ermitteln |
| `vorgaenge:purge` | Abgeschlossene Vorgänge nach Aufbewahrungsfrist löschen |

Die drei wiederkehrenden Aufgaben sind in `routes/console.php` eingeplant; dafür
muss `php artisan schedule:work` (oder ein Cron-Eintrag) laufen.

## Wenn der Webhook nichts liefert

`php artisan kobo:check` prüft der Reihe nach Zugangsdaten, API, Geheimnis,
öffentliche Erreichbarkeit, den in KoboToolbox eingetragenen REST Service und
die tatsächlich eingegangenen Rohdaten – und nennt zu jedem Fehler den nächsten
Schritt. Dieselbe Prüfung gibt es im Browser unter **System → Kobo-Verbindung**.

Damit der Webhook überhaupt funktionieren kann, müssen zwei Dinge stimmen:

1. Die Anwendung ist aus dem Internet erreichbar, und `APP_URL` zeigt auf genau
   diese Adresse. Ein `localhost` erreicht KoboToolbox nicht.
2. In KoboToolbox ist unter **Settings → REST Services** ein Dienst auf
   `<APP_URL>/api/webhooks/kobo` eingetragen, mit dem Header `X-Kobo-Secret`.

Solange das nicht steht – und auch danach, falls eine Zustellung fehlschlägt –
holt der Knopf **„Aus KoboToolbox holen“** (auf der Vorgangsliste, bei den
Rohdaten und auf der Seite Kobo-Verbindung) die Meldungen direkt über die API.
Bereits vorhandene werden übersprungen, doppelte Vorgänge entstehen nicht.

## Workflow

Statusliste und Prioritätslogik stammen aus der bisherigen Monitor-Anwendung:

```
Neu → In Prüfung → Bestätigt → In Bearbeitung → Bekämpft
                   ↘ Kein Handlungsbedarf (aus jedem Status)
```

Die Priorität wird aus der Gefährdungsbewertung der Verwaltung berechnet
(Gefährdung von Menschen/Tieren → **Hoch**, Schutzgebiet oder sensible Natur →
**Zeitnah**, sonst **Normal**) und kann manuell überschrieben werden.

Status und Übergänge sind Datensätze und im Bereich **System** änderbar.

## Datenschutz

* Fotos liegen auf einer nicht öffentlichen Ablage und werden nur über signierte,
  ablaufende Links ausgeliefert.
* Meldende Personen erhalten **nur** dann E-Mails, wenn sie eine Adresse
  hinterlassen **und** der Kontaktaufnahme zugestimmt haben.
* Die Datenschutzerklärung auf der öffentlichen Seite wurde entsprechend
  ergänzt; der Text ist vor dem Produktivbetrieb rechtlich zu prüfen.

## Tests

```bash
php artisan test
```
