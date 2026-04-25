# KR-Forms WordPress Plugin

KR-Forms ist ein WordPress-Plugin zum Erstellen eigener Formulare mit Formular-Baukasten, formularbezogenem Design, SMTP-Funktion und Sicherheitsfunktionen ohne externe Captcha-Dienste.

## Funktionen

- Formular-Baukasten im WordPress-Admin
- Eigene Formularübersicht und separater Editor pro Formular
- Pro Formular eigener E-Mail-Betreff, eigener E-Mail-Text und eigenes Design
- Pro Formular optionale Bestätigungs-E-Mail mit Formularzusammenfassung an den Kunden
- Feld-Labels unterstützen sichere Links mit `<a href="https://domain.tld">Linktext</a>`
- Transparenter Formularhintergrund, transparenter Feld-Hintergrund und transparente Hinweisboxen pro Formular
- Live-Vorschau im Editor mit speicherbarem Vorschau-Hintergrund
- E-Mail-Einstellungen für Empfänger, Absender, Reply-To und SMTP
- SMTP-Testversand im Admin
- Eigenes SVG-Captcha ohne externe Dienste
- Rate-Limiting pro Formular und IP
- Trusted-Proxies-Konfiguration für Reverse-Proxy- und Docker-Setups
- Sicherheitsprotokoll in eigener Datenbanktabelle und optionale Alarm-E-Mail
- Allgemeines Protokoll für alle Formularanfragen
- Vorgefertigte Beispiele für Kontaktformular und Widerrufsformular
- Shortcode-Ausgabe per `[kr-forms id="kontaktformular"]`
- Frontend-Validierung und Spam-Schutz über Honeypot

## Installation

1. Ordner `KR-Forms` in `wp-content/plugins/` kopieren.
2. Plugin `KR-Forms` in WordPress aktivieren.
3. Die Hauptdatei des Plugins ist `kr-forms.php`.
4. Unter `KR-Forms` Formulare anlegen oder bearbeiten.
5. Unter `KR-Forms > Einstellungen` Mailversand, SMTP, Sicherheit und Updates konfigurieren.
6. Dateirechte und Besitzrechte auf dem Server müssen korrekt gesetzt sein, damit WordPress das Plugin schreiben, sichern und aktualisieren kann. Falsche Rechte können z. B. dazu führen, dass Updates mit Fehlern beim Verschieben in `upgrade-temp-backup` fehlschlagen.

## Nutzung

- Kontaktformular: `[kr-forms id="kontaktformular"]`
- Widerrufsformular: `[kr-forms id="widerrufsformular"]`

Weitere Formulare können im Admin erstellt und dann mit ihrer jeweiligen ID eingebunden werden.

## Formulareditor

Die Bereiche im Editor sind in dieser Reihenfolge angeordnet:

1. Allgemein
2. Felder
3. E-Mail
4. Design

Die Option für die Bestätigungs-E-Mail an den Kunden wird direkt pro Formular im Bereich `E-Mail` gesetzt.

Feld-Labels dürfen Links enthalten. Beispiel:

```html
Hiermit bestätige ich den Widerruf gemäß den <a href="https://domain.tld/agb">AGBs</a>.
```

Wichtig: Links müssen mit einer vollständigen URL wie `https://domain.tld` angegeben werden.
Wird `https://` weggelassen und z. B. nur `domain.tld` eingetragen, behandelt der Browser den Wert als relative URL zur Formularseite.
Dadurch kann aus `domain.tld` je nach Einbindungsseite z. B. `https://domain.tld/test/domain.tld` werden.

In der Formularzusammenfassung per E-Mail bleiben diese Links erhalten und werden im Textformat als Linktext mit URL ausgegeben.

## Platzhalter für den E-Mail-Text

- `{form_name}`
- `{page_url}`
- `{submitted_at}`
- `{all_fields}`
- `{field:name}` für ein einzelnes Feld, z. B. `{field:email}`

`{submitted_at}` wird im Format `DD.MM.YYYY HH:MM:SS` ausgegeben.

## SMTP und Sicherheit

- SMTP-Host, Port, Benutzername und Passwort werden in den Plugin-Einstellungen gespeichert.
- Das SMTP-Passwort wird verschlüsselt in der Datenbank gespeichert.
- Optional kann `KR_FORMS_SMTP_PASSWORD` in `wp-config.php` oder als Umgebungsvariable verwendet werden. Dieser Wert hat Vorrang.
- Unter `Trusted Proxies` können einzelne IPs, CIDR-Bereiche oder Wildcards eingetragen werden, z. B. `172.19.*.*` oder `172.19.0.0/16`.
- Proxy-Header wie `X-Forwarded-For` werden nur ausgewertet, wenn `REMOTE_ADDR` zu einem konfigurierten Trusted Proxy passt.

## Updates

- Das Plugin unterstützt einen eigenen Update-Feed außerhalb des WordPress-Plugin-Verzeichnisses.
- Die URL dazu wird in den Plugin-Einstellungen unter `Update-Metadaten-URL` hinterlegt.
- Sobald dort eine gültige JSON-URL eingetragen ist, erscheinen verfügbare Updates im WordPress-Plugin-Manager.
- Automatische Updates können anschließend direkt im Plugin-Manager von WordPress aktiviert werden.

Beispiel für die Update-Metadaten:

```json
{
  "version": "2.2.0",
  "download_url": "https://domain.tld/downloads/kr-forms-2.2.0.zip",
  "details_url": "https://domain.tld/kr-forms/",
  "homepage": "https://domain.tld/kr-forms/",
  "requires": "6.4",
  "tested": "6.8.3",
  "requires_php": "7.4",
  "last_updated": "2026-04-25 12:00:00",
  "sections": {
    "description": "<p>KR-Forms Update-Feed</p>",
    "installation": "<p>ZIP herunterladen und aktualisieren.</p>",
    "changelog": "<p><strong>2.2.0</strong><br>Eigene Plugin-Tabelle für Einstellungen und Formulare, plus externer Update-Feed.</p>"
  }
}
```

## Speicherung

- Formulare, Design, SMTP-, Mail-, Sicherheits- und Update-Einstellungen werden in der eigenen Tabelle `wp_kr_forms_settings` gespeichert. Bei abweichendem Tabellenpräfix wird das jeweilige WordPress-Präfix verwendet.
- Eigene Plugin-Daten werden damit nicht mehr in `wp_options` gespeichert.
- Sicherheitsereignisse werden in der Tabelle `wp_kr_forms_security_log` gespeichert. Bei abweichendem Tabellenpräfix wird das jeweilige WordPress-Präfix verwendet.
- Allgemeine Formularanfragen werden in der Tabelle `wp_kr_forms_request_log` gespeichert. Bei abweichendem Tabellenpräfix wird das jeweilige WordPress-Präfix verwendet.

## Protokolle

- `Sicherheitsprotokoll` speichert blockierte oder auffällige Ereignisse inklusive IP-Adresse.
- `Allgemeines Protokoll` speichert erfolgreiche und fehlgeschlagene Formularanfragen ohne IP-Anzeige im Admin.
- Das allgemeine Protokoll enthält zusätzlich eine Formularzusammenfassung, damit Eingaben auch bei Mailfehlern nachvollziehbar bleiben.
- Beide Protokolle können im Admin geleert werden.

## Changelog

### 2.2.1

- Formulare werden nicht mehr in `wp_options`, sondern ebenfalls in der eigenen Tabelle `wp_kr_forms_settings` gespeichert.
- Vorhandene Formulare aus `kr_forms_forms` werden automatisch in die eigene Plugin-Tabelle übernommen.
- `KR-Forms` arbeitet damit für eigene Konfigurations- und Formulardaten vollständig ohne `wp_options`.
- Externe Updatefunktion über `update.json` ergänzt, inklusive manueller und automatischer Updates über den WordPress-Plugin-Manager.

### 2.1.0

- Plugin-Einstellungen werden nicht mehr in `wp_options`, sondern in der eigenen Tabelle `wp_kr_forms_settings` gespeichert.
- Vorhandene Einstellungen aus `kr_forms_email_settings` und `kr_forms_design_settings` werden automatisch in die neue Tabelle übernommen.
- Eigener Update-Mechanismus für den WordPress-Plugin-Manager ergänzt.
- Updates können über eine konfigurierbare JSON-Metadaten-URL bereitgestellt werden.
- Manuelle Updates und automatische Updates über den Plugin-Manager werden damit unterstützt.

### 2.0.2

- Neue Design-Option für den Absenden-Button: `rund` oder `eckig`.
- Live-Vorschau und Frontend-Ausgabe berücksichtigen jetzt die gewählte Button-Form.
- Verbliebene interne Alt-Bezeichner aus der Umstellung wurden entfernt und auf die aktuelle `KR-Forms`-Namensstruktur bereinigt.

### 2.0.1

- BOM am Anfang der PHP-Dateien entfernt, damit der WordPress-Block-Editor beim Speichern keine ungültige JSON-Antwort mehr meldet.

### 2.0.0

- Technische Namensstruktur auf `KR-Forms`, `kr_forms` und `kr-forms` vereinheitlicht.
- Neuer Shortcode: `[kr-forms id="..."]`.
- Admin-Slugs, Actions, Nonces, Query-Parameter und DOM-/CSS-Präfixe wurden auf `kr-forms` bzw. `kr_forms` umgestellt.
- Optionsnamen wurden auf `kr_forms_*` umgestellt.
- Datenbanktabellen wurden auf `wp_kr_forms_*` umgestellt.
- Hauptdatei des Plugins ist jetzt `kr-forms.php`.
- SMTP-Konstante wurde auf `KR_FORMS_SMTP_PASSWORD` umgestellt.

Wichtig:
- Der verwendete Shortcode ist `[kr-forms ...]`.
- Bereits vorhandene Installationen benötigen wegen der neuen Optionsnamen und Tabellen eine bewusste Migration, wenn alte Daten übernommen werden sollen.

### 1.1.0

- Feld-Labels unterstützen jetzt sichere Links mit `<a href="https://domain.tld">Linktext</a>`.
- Links in Labels werden auch in der Formularzusammenfassung per E-Mail als Linktext mit URL ausgegeben.
- Die Tabellenansicht im allgemeinen Protokoll wurde für lange URLs, Zusammenfassungen und Details verbessert.
