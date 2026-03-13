# mindpool – Trainer*innen-Pool für mindscool

Webapplikation zur Verwaltung des Trainer*innen-Pools der gemeinnützigen Organisation **mindscool** ("a school for the mind"). Aktive Trainer*innen und Dozent*innen können ihre persönlichen Daten, akademischen Abschlüsse und Projekterfahrung pflegen.

## Funktionen

- Zentrale tabellarische Übersicht aller aktiven Trainer*innen
- Interaktive Karte (OpenStreetMap) mit Standorten und Einsatzgebieten
- PDF-Profile pro Person (Einzel- und Sammel-Export)
- Kontrollierte Vokabulare (Taxonomien) für Abschlussarten, Fächer und Einsatzgebiete
- Vorschlagsmechanismus für neue Taxonomie-Einträge
- Einfacher Passwortschutz (geteiltes Passwort, bcrypt-Hash)

## Voraussetzungen

- **PHP** >= 7.4 mit PHP-FPM
- **Nginx** (kein Apache!)
- Schreibrechte auf das `data/`-Verzeichnis

## Installation

### 1. Dateien auf den Server hochladen

Alle Dateien in das gewünschte Webverzeichnis kopieren, z.B. `/var/www/mindpool/`.

### 2. Schreibrechte setzen

```bash
chmod 770 /var/www/mindpool/data/
chown www-data:www-data /var/www/mindpool/data/
```

### 3. Passwort setzen

Ein bcrypt-Hash generieren:

```bash
php -r "echo password_hash('MeinSicheresPasswort', PASSWORD_BCRYPT) . PHP_EOL;"
```

Den ausgegebenen Hash in `config.php` bei `AUTH_HASH` eintragen:

```php
define('AUTH_HASH', '$2y$10$HIER_DEN_HASH_EINTRAGEN...');
```

### 4. Nginx-Konfiguration einbinden

In den `server`-Block der Nginx-Konfiguration einfügen:

```nginx
server {
    listen 443 ssl;
    server_name meinedomain.de;
    root /var/www/mindpool;
    index index.php;

    # mindpool-Konfiguration einbinden
    include /var/www/mindpool/nginx.conf.example;
}
```

Danach Nginx prüfen und neu laden:

```bash
nginx -t && nginx -s reload
```

### 5. Sicherheit prüfen

**WICHTIG:** Nach der Einrichtung testen, dass die Datendateien nicht öffentlich erreichbar sind:

```bash
curl -I https://meinedomain.de/data/dozenten.json
# Muss 403 Forbidden zurückgeben!

curl -I https://meinedomain.de/config.php
# Muss 403 Forbidden zurückgeben!
```

### 6. Taxonomie-Stammdaten anpassen

Die Datei `data/taxonomien.json` enthält vordefinierte Abschlussarten, Fächer und Einsatzgebiete. Diese können vor dem ersten Start an die konkreten Bedürfnisse angepasst werden. Später lassen sich Einträge auch über die Verwaltungsoberfläche (⚙ Stammdaten) pflegen.

## Passwort ändern

```bash
php -r "echo password_hash('NeuesPasswort', PASSWORD_BCRYPT) . PHP_EOL;"
```

Den neuen Hash in `config.php` eintragen und – falls Nginx oder PHP-FPM OPcache nutzt – den Cache leeren.

## Backup

Regelmäßig sichern:

```bash
cp data/dozenten.json data/dozenten_backup_$(date +%Y%m%d).json
cp data/taxonomien.json data/taxonomien_backup_$(date +%Y%m%d).json
```

## Troubleshooting

| Problem | Lösung |
|---------|--------|
| „500 Internal Server Error" | Schreibrechte auf `data/` prüfen: `chmod 770 data/` und `chown www-data:www-data data/` |
| Login funktioniert nicht | Prüfen, ob der bcrypt-Hash korrekt in `config.php` eingetragen ist |
| Session-Fehler | PHP-Session-Pfad prüfen: `php -i \| grep session.save_path` – Verzeichnis muss existieren und beschreibbar sein |
| Nginx zeigt leere Seite | `include /pfad/zu/nginx.conf.example;` muss im `server`-Block stehen. `nginx -t` zeigt Fehler |
| Karte lädt nicht | Prüfen, ob die CSP-Header korrekt sind und `*.tile.openstreetmap.org` erlaubt ist |
| Fonts werden nicht geladen | Dateien unter `assets/fonts/` prüfen, `font-src 'self'` in der CSP muss gesetzt sein |

## Datenschutz

Die App überträgt **keine Daten an Dritte** (insbesondere nicht an Google):

- **Fonts:** Titillium Web ist lokal unter `assets/fonts/` eingebunden (kein Google Fonts)
- **Karten:** OpenStreetMap-Tiles von `tile.openstreetmap.org` – [Datenschutzhinweise von OpenStreetMap](https://wiki.osmfoundation.org/wiki/Privacy_Policy)
- **Geocoding:** Nominatim (OpenStreetMap) überträgt nur den Ortsnamen zur Koordinatenermittlung, keine personenbezogenen Daten
- **CDN-Bibliotheken:** Leaflet.js und jsPDF werden von Cloudflare CDN (`cdnjs.cloudflare.com`) geladen

## Technische Architektur

- **Backend:** Plain PHP (kein Framework, kein Composer)
- **Datenbank:** JSON-Dateien (`data/dozenten.json`, `data/taxonomien.json`)
- **Frontend:** Vanilla JavaScript, CSS mit Custom Properties
- **Karten:** Leaflet.js mit OpenStreetMap
- **PDF:** Clientseitig mit jsPDF
- **Webserver:** Nginx mit PHP-FPM

## Lizenz

Siehe [LICENSE](LICENSE).
