# Schmitke Fenster Konfigurator (V2)

Ein moderner Fenster-Konfigurator als WordPress-Plugin für Schmitke Bauelemente. Kunden können Fenster-Varianten mit allen relevanten Optionen auswählen und die Konfiguration als E-Mail-Anfrage an den Betrieb senden.

## ✨ Features
- **WordPress-Shortcode**: `[schmitke_windows_configurator]` rendert den Fenster-Konfigurator im Frontend.
- **V2 Admin-Panel**: Elemente, Optionen, Regeln und Design-Token werden zentral gepflegt.
- **WordPress-Mediathek**: Bilder werden direkt aus der Mediendatenbank gewählt.
- **Automatische E-Mail**: Zusammenfassung der Auswahl wird an die hinterlegte Zieladresse versendet (inkl. PDF).
- **JSON-basierte Konfiguration**: Alle Einstellungen werden als einzelner Optionswert gespeichert.
- **Sanitizing & Validation**: Admin-Eingaben werden konsequent bereinigt, bevor sie gespeichert werden.

## 📦 Installation
1. Das Repository in den `wp-content/plugins/` Ordner kopieren (Ordnername: `window-door-configurator`).
2. Im WordPress-Backend unter **Plugins** das Plugin **Schmitke Fenster Konfigurator – v2** aktivieren.
3. Optional: Die Standarddaten können im Admin-Bereich sofort angepasst werden.

## 🚀 Nutzung
1. Erstelle eine Seite oder einen Beitrag und füge den Shortcode ein:
   ```
   [schmitke_windows_configurator]
   ```
2. Speichere die Seite. Der Konfigurator lädt sein Styling (`public/configurator.css`) und Verhalten (`public/configurator-windows.js`) automatisch nur, wenn der Shortcode vorhanden ist.
3. Im Fenster-Konfigurator (V2) können mehrere Positionen gespeichert werden. Jede Position erhält einen Namen und erscheint als aufklappbare Zusammenfassung in der Summary-Box.
4. Die Reihenfolge der Zusammenfassung entspricht immer der Reihenfolge der Elemente auf der Seite (nicht der Klick-Reihenfolge).
5. Über **Angebot anfragen** werden die Kontaktdaten erfasst und eine PDF-Zusammenfassung per E-Mail an die konfigurierte Empfängeradresse gesendet.

## 🔧 Konfiguration im Admin-Bereich
Die Einstellungen findest du unter **Einstellungen → Fenster Konfigurator**. Dort können die V2-Elemente, Optionen, Regeln sowie Design- und UI-Einstellungen gepflegt werden. Alternativ steht ein JSON-Fallback zur Verfügung.

## 🗂️ V2 Settings (zentral)
Für frei editierbare Elemente/Optionen wird ein einziges Optionsobjekt `schmitke_configurator_settings_v2` genutzt. Es enthält:
- `elements`: Metadaten zu allen Elementen (Key, Typ `single|multi|measurements|upload`, Labels DE/EN, Sichtbarkeit, Pflicht, Accordion-Default, Order, Search-Flag, Spaltenbreiten).
- `options_by_element`: Optionslisten je Element (option_code, Labels DE/EN, Info-Texte DE/EN, Bild-ID, Default-Flag, Preis/Einheit, Disabled).
- `rules`: Bedingungslogik (`when` mit AND/OR + `then` Aktionen: show/hide/filter/disable/set_required/unset_required).
- `global_ui`: Sticky Summary + Accordion Toggle + Locale Mode.

Die Seed-Daten decken alle geforderten Elemente ab (Typ, Material, Verglasung, Maße & Anzahl, Ornamentglas, Schallschutzglas, Kundenfoto-Upload etc.). Details und Beispiele siehe `docs/CONFIG_SETTINGS_V2.md`.

## 🧰 Entwicklung & Hinweise
- Frontend-Assets werden nur geladen, wenn der Shortcode im Inhalt vorkommt.
- Angebotsanfragen werden über `admin-ajax.php` verarbeitet (`schmitke_windows_request_quote`). Die PDF-Zusammenfassung wird serverseitig erstellt und mit `wp_mail` versendet.

## 📁 Projektstruktur
- `schmitke-door-configurator.php` – Haupt-Plugin-Datei mit Shortcode und V2-Admin-Page.
- `includes/config-settings-v2.php` – Zentrales Settings-Format `schmitke_configurator_settings_v2` (Seed, Sanitizing, Registrierung).
- `admin/configurator-v2.js` – UI-Logik für die V2-Einstellungen im WordPress-Backend.
- `public/configurator-windows.js` – Frontend-Rendering des Fenster-Konfigurators.
- `public/configurator.css` – Frontend-Styling des Konfigurators.

## 🗺️ Architecture Map
- `schmitke-door-configurator.php`: Bootstrapping, Shortcode, Asset-Registrierung, AJAX, PDF-Generierung.
- `includes/config-settings-v2.php`: Zentrales Settings-Format `schmitke_configurator_settings_v2` (Seed, Sanitizing, Registrierung).
- `admin/configurator-v2.js`: V2-Admin-UI für Elemente, Optionen, Regeln und Design-Token.
- `public/configurator-windows.js`: Rendering-Logik und Interaktionen für den V2-Fenster-Konfigurator.

## 🆘 Support
Fragen oder Feature-Wünsche können im Projekt-Repository als Issue erfasst werden.
