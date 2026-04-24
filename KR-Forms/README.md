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
- Shortcode-Ausgabe per `[formulare id="kontaktformular"]`
- Frontend-Validierung und Spam-Schutz über Honeypot

## Installation

1. Ordner `KR-Forms` in `wp-content/plugins/` kopieren.
2. Plugin `KR-Forms` in WordPress aktivieren.
3. Unter `KR-Forms` Formulare anlegen oder bearbeiten.
4. Unter `KR-Forms > E-Mail-Einstellungen` Mailversand, SMTP und Sicherheit konfigurieren.

## Nutzung

- Kontaktformular: `[formulare id="kontaktformular"]`
- Widerrufsformular: `[formulare id="widerrufsformular"]`

Weitere Formulare können im Admin erstellt und dann mit ihrer jeweiligen ID eingebunden werden.

Hinweis zur Kompatibilität:
- Der Shortcode bleibt aus Kompatibilitätsgründen weiterhin `[formulare ...]`.
- Interne Optionsnamen, Action-Slugs und Datenbanktabellen bleiben ebenfalls unverändert bei `formulare_*`.

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
- Optional kann weiterhin `FORMULARE_SMTP_PASSWORD` in `wp-config.php` oder als Umgebungsvariable verwendet werden. Dieser Wert hat Vorrang.
- Unter `Trusted Proxies` können einzelne IPs, CIDR-Bereiche oder Wildcards eingetragen werden, z. B. `172.19.*.*` oder `172.19.0.0/16`.
- Proxy-Header wie `X-Forwarded-For` werden nur ausgewertet, wenn `REMOTE_ADDR` zu einem konfigurierten Trusted Proxy passt.

## Speicherung

- SMTP- und Mail-Einstellungen werden in `wp_options` unter `formulare_email_settings` gespeichert.
- Formulare inklusive Feldern, E-Mail-Text, Kunden-Bestätigung und Design werden in `wp_options` unter `formulare_forms` gespeichert.
- Sicherheitsereignisse werden in der Tabelle `wp_formulare_security_log` gespeichert. Bei abweichendem Tabellenpräfix wird das jeweilige WordPress-Präfix verwendet.
- Allgemeine Formularanfragen werden in der Tabelle `wp_formulare_request_log` gespeichert. Bei abweichendem Tabellenpräfix wird das jeweilige WordPress-Präfix verwendet.

## Protokolle

- `Sicherheitsprotokoll` speichert blockierte oder auffällige Ereignisse inklusive IP-Adresse.
- `Allgemeines Protokoll` speichert erfolgreiche und fehlgeschlagene Formularanfragen ohne IP-Anzeige im Admin.
- Das allgemeine Protokoll enthält zusätzlich eine Formularzusammenfassung, damit Eingaben auch bei Mailfehlern nachvollziehbar bleiben.
- Beide Protokolle können im Admin geleert werden.

## Changelog

### 1.1.0

- Feld-Labels unterstützen jetzt sichere Links mit `<a href="https://domain.tld">Linktext</a>`.
- Links in Labels werden auch in der Formularzusammenfassung per E-Mail als Linktext mit URL ausgegeben.
- Die Tabellenansicht im allgemeinen Protokoll wurde für lange URLs, Zusammenfassungen und Details verbessert.
- Das Plugin wurde sichtbar auf den neuen Namen `KR-Forms` umgestellt.
