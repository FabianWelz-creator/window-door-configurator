# Schmitke Türen Konfigurator (MVP)

Ein kompakter WordPress-Plugin-Prototyp, der einen Türen-Konfigurator als Shortcode bereitstellt.
Admins pflegen Modelle, Maße, Farben und Bilder direkt über die Einstellungsseite, während Besucher ihre Wunschkonfiguration zusammenklicken und per E‑Mail anfragen können.

## Features
- **Shortcode** `[schmitke_doors_configurator]` rendert den Konfigurator auf beliebigen Seiten.
- **Design-Optionen** (Farben, Radius, Schrift) werden als CSS-Variablen ins Frontend geschrieben.
- **Modelldaten** inklusive WP-Mediathek-Bildern, DIN-Maßen, Zargen, Extras und Regeln.
- **E-Mail-Workflow**: Button erzeugt vorbefüllte Mail, Copy-Button kopiert die Konfiguration in die Zwischenablage.
- **Admin-Komfort**: Medienauswahl, Farbwähler und dynamische Karten für Modelle.

## Installation
1. Repository in das `wp-content/plugins/` Verzeichnis kopieren oder als ZIP installieren.
2. Das Plugin im WordPress-Backend unter **Plugins** aktivieren.
3. Unter **Einstellungen → Türen Konfigurator** die Stammdaten (Empfänger-Mail, Modelle, Maße etc.) pflegen.

## Nutzung
- Füge in einer Seite oder einem Beitrag den Shortcode ein:

  ```
  [schmitke_doors_configurator]
  ```

- Der Konfigurator lädt die konfigurierten Modelle, ermöglicht die Auswahl von Maßen, Zargen, Extras und generiert eine vorbefüllte Angebots-Mail.

## Entwicklung
- Assets liegen in `public/` (Frontend) und `admin/` (Backend).
- Die Hauptlogik befindet sich in `schmitke-door-configurator.php`.
- Versionskonstante `VERSION` wird für Asset-Versionierung genutzt.

## Support
Dies ist ein MVP. Fehlermeldungen oder Feature-Ideen bitte mit möglichst detaillierter Beschreibung der Schritte zur Reproduktion melden.
