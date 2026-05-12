# mindpool – Trainer*innen-Pool für mindscool

Webapplikation zur Verwaltung des Trainer*innen-Pools der gemeinnützigen Organisation **mindscool** ("a school for the mind"). Aktive Trainer*innen und Dozent*innen können ihre persönlichen Daten, akademischen Abschlüsse und Projekterfahrung pflegen. Zusätzlich werden die Orte (Schulen, Universitäten, Lehrerinstitute, sonstige Institutionen) gepflegt, an denen mindscool tätig ist.

## Funktionen

- Zentrale tabellarische Übersicht aller aktiven Trainer*innen
- Verwaltung der Einsatzorte (Schulen, Universitäten, Lehrerinstitute, sonstige Institutionen) mit Typisierung
- Interaktive Karte (OpenStreetMap) mit Trainer*innen-Standorten **und** Einsatzorten – ein Layer-Toggle blendet die jeweilige Ebene ein/aus
- PDF-Profile pro Person (Einzel- und Sammel-Export)
- Kontrollierte Vokabulare (Taxonomien) für Abschlussarten, Fächer, Einsatzgebiete und Ort-Typen
- Vorschlagsmechanismus für neue Taxonomie-Einträge
- Einfacher Passwortschutz (geteiltes Passwort, bcrypt-Hash)

## Voraussetzungen

- **PHP** >= 7.4 (mit PHP-FPM oder mod_php)
- **Webserver:** Nginx **oder** Apache 2.4+
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

### 4. Webserver konfigurieren

mindpool unterstützt zwei Webserver. Wähle die passende Variante:

#### Variante A: Nginx

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

#### Variante B: Apache 2.4+

mindpool bringt zwei `.htaccess`-Dateien mit:

- `.htaccess` im Web-Root – blockiert `config.php` und `auth.php`, setzt die Security-Header und die CSP
- `data/.htaccess` – blockiert das Datenverzeichnis als zweite Schutzschicht

Voraussetzungen:

- Apache 2.4 oder neuer
- Aktivierte Module: `mod_rewrite`, `mod_headers`, `mod_authz_core`
- PHP über `mod_php` **oder** PHP-FPM via `mod_proxy_fcgi` / `mod_php`
- Im umgebenden VHost: `AllowOverride All`

Minimal-VHost-Beispiel (PHP-FPM via `mod_proxy_fcgi`):

```apache
<VirtualHost *:443>
    ServerName meinedomain.de
    DocumentRoot /var/www/mindpool

    SSLEngine on
    SSLCertificateFile      /etc/ssl/.../fullchain.pem
    SSLCertificateKeyFile   /etc/ssl/.../privkey.pem

    <Directory /var/www/mindpool>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    # PHP-FPM (Pfad an die lokale Konfiguration anpassen)
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog  ${APACHE_LOG_DIR}/mindpool-error.log
    CustomLog ${APACHE_LOG_DIR}/mindpool-access.log combined
</VirtualHost>
```

Module aktivieren und Apache neu laden:

```bash
a2enmod rewrite headers proxy_fcgi
a2ensite mindpool
apachectl configtest && systemctl reload apache2
```

### 5. Sicherheit prüfen

**WICHTIG:** Nach der Einrichtung testen, dass die Datendateien nicht öffentlich erreichbar sind (Tests funktionieren für Nginx und Apache identisch):

```bash
curl -I https://meinedomain.de/data/dozenten.json
# Muss 403 Forbidden zurückgeben!

curl -I https://meinedomain.de/data/orte.json
# Muss 403 Forbidden zurückgeben!

curl -I https://meinedomain.de/config.php
# Muss 403 Forbidden zurückgeben!
```

### 6. Taxonomie-Stammdaten anpassen

Die Datei `data/taxonomien.json` enthält vordefinierte Abschlussarten, Fächer, Einsatzgebiete und Ort-Typen. Diese können vor dem ersten Start an die konkreten Bedürfnisse angepasst werden. Später lassen sich Einträge auch über die Verwaltungsoberfläche (⚙ Stammdaten) pflegen.

### 7. Einsatzorte pflegen

Die Pflege der Orte (Schulen, Universitäten, Lehrerinstitute, sonstige Institutionen) erfolgt über die Seite **📍 Orte** im Header. Jeder Ort wird per UUID geschlüsselt und mit einem Typ aus der Taxonomie `ort_typen` versehen. Auf der Karte werden Orte mit einem eingefärbten Marker dargestellt – Trainer- und Orte-Layer können einzeln ein-/ausgeblendet werden.

## Passwort ändern

```bash
php -r "echo password_hash('NeuesPasswort', PASSWORD_BCRYPT) . PHP_EOL;"
```

Den neuen Hash in `config.php` eintragen und – falls Nginx oder PHP-FPM OPcache nutzt – den Cache leeren.

## Backup

Regelmäßig sichern:

```bash
cp data/dozenten.json   data/dozenten_backup_$(date +%Y%m%d).json
cp data/orte.json       data/orte_backup_$(date +%Y%m%d).json
cp data/taxonomien.json data/taxonomien_backup_$(date +%Y%m%d).json
```

## Troubleshooting

| Problem | Lösung |
|---------|--------|
| „500 Internal Server Error" | Schreibrechte auf `data/` prüfen: `chmod 770 data/` und `chown www-data:www-data data/` |
| Login funktioniert nicht | Prüfen, ob der bcrypt-Hash korrekt in `config.php` eingetragen ist |
| Session-Fehler | PHP-Session-Pfad prüfen: `php -i \| grep session.save_path` – Verzeichnis muss existieren und beschreibbar sein |
| Nginx zeigt leere Seite | `include /pfad/zu/nginx.conf.example;` muss im `server`-Block stehen. `nginx -t` zeigt Fehler |
| Apache: `.htaccess` wirkt nicht | Im VHost muss `AllowOverride All` gesetzt sein. `mod_rewrite` und `mod_headers` müssen aktiviert sein (`apachectl -M \| grep -E 'rewrite\|headers'`). |
| Apache: `data/dozenten.json` ist erreichbar | Prüfen, ob `data/.htaccess` existiert und ausgeliefert wurde (kein `chmod 000`). Im Zweifel über VHost mit `<Directory>`+`Require all denied` doppelt absichern. |
| Karte lädt nicht | Prüfen, ob die CSP-Header korrekt sind und `*.tile.openstreetmap.org` erlaubt ist |
| Geocoding setzt keine Koordinaten | `connect-src` in der CSP muss `https://nominatim.openstreetmap.org` enthalten – sowohl in `nginx.conf.example` als auch in `.htaccess` schon vorgesehen |
| Orte erscheinen nicht auf der Karte | Layer-Toggle oben rechts auf der Karte prüfen; in `ort-form.php` muss der Standort gesetzt sein (`lat`/`lng` ≠ 0) |
| Fonts werden nicht geladen | Dateien unter `assets/fonts/` prüfen, `font-src 'self'` in der CSP muss gesetzt sein |

## Datenschutz

Die App überträgt **keine Daten an Dritte** (insbesondere nicht an Google):

- **Fonts:** Titillium Web ist lokal unter `assets/fonts/` eingebunden (kein Google Fonts)
- **Karten:** OpenStreetMap-Tiles von `tile.openstreetmap.org` – [Datenschutzhinweise von OpenStreetMap](https://wiki.osmfoundation.org/wiki/Privacy_Policy)
- **Geocoding:** Nominatim (OpenStreetMap) überträgt nur den Ortsnamen zur Koordinatenermittlung, keine personenbezogenen Daten
- **CDN-Bibliotheken:** Leaflet.js und jsPDF werden von Cloudflare CDN (`cdnjs.cloudflare.com`) geladen

## Technische Architektur

- **Backend:** Plain PHP (kein Framework, kein Composer)
- **Datenbank:** JSON-Dateien (`data/dozenten.json`, `data/orte.json`, `data/taxonomien.json`)
- **Frontend:** Vanilla JavaScript, CSS mit Custom Properties
- **Karten:** Leaflet.js mit OpenStreetMap, zwei Marker-Layer (Trainer*innen + Orte)
- **PDF:** Clientseitig mit jsPDF
- **Webserver:** Nginx mit PHP-FPM **oder** Apache 2.4+ (mod_php / mod_proxy_fcgi)

## Lizenz

Siehe [LICENSE](LICENSE).
