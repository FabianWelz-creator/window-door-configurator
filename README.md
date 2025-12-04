# Schmitke Türen & Fenster Konfigurator

Ein moderner Türen- und Fenster-Konfigurator als WordPress-Plugin für Schmitke Bauelemente. Kunden können Tür- und Fenster-Modelle mit allen relevanten Optionen auswählen und die Konfiguration als E-Mail-Anfrage an den Betrieb senden.

## ✨ Features
- **WordPress-Shortcodes**: `[schmitke_doors_configurator]` rendert den Türen-Konfigurator, `[schmitke_windows_configurator]` den Fenster-Konfigurator im Frontend.
- **Admin-Panel**: Modelle, Größen, Kanten, Regeln und Design-Optionen werden zentral gepflegt.
- **WordPress-Mediathek**: Bilder werden direkt aus der Mediendatenbank gewählt.
- **Automatische E-Mail**: Zusammenfassung der Auswahl wird an die hinterlegte Zieladresse versendet.
- **JSON-basierte Konfiguration**: Alle Einstellungen werden als einzelner Optionswert gespeichert.
- **Sanitizing & Validation**: Admin-Eingaben werden konsequent bereinigt, bevor sie gespeichert werden.

## 📦 Installation
1. Das Repository in den `wp-content/plugins/` Ordner kopieren (Ordnername: `window-door-configurator`).
2. Im WordPress-Backend unter **Plugins** das Plugin **Schmitke Türen Konfigurator (MVP) – v2.1** aktivieren.
3. Optional: Die Standarddaten können im Admin-Bereich sofort angepasst werden.

## 🚀 Nutzung
1. Erstelle eine Seite oder einen Beitrag und füge je nach Bedarf einen Shortcode ein:
   ```
   [schmitke_doors_configurator]
   [schmitke_windows_configurator]
   ```
2. Speichere die Seite. Der jeweilige Konfigurator lädt sein Styling (`public/configurator.css`) und Verhalten (`public/configurator.js` bzw. `public/configurator-windows.js`) automatisch nur, wenn der Shortcode vorhanden ist.

## 🔧 Konfiguration im Admin-Bereich
Die Einstellungen findest du unter **Einstellungen → Türen Konfigurator**.

### E-Mail
- **Empfänger E-Mail**: Adresse, an die Anfragen gesendet werden.

### Design
- Primär-/Akzent-/Text-/Border-Farben (Color-Picker)
- Schriftfamilie
- Button-Radius, Karten-Radius

### Türmodelle
Für jedes Modell stehen folgende Felder zur Verfügung:
- Familie & Modellname
- Finish
- Standard-Bauart (`stumpf` oder `gefaelzt`)
- Lichtausschnitt-Optionen (kommagetrennte Liste)
- Kantenausführung Default
- Bildauswahl über die WordPress-Mediathek

### Maße
- DIN-Breiten (mm) und DIN-Höhen (mm) als kommagetrennte Listen.

### Rahmen & Extras
- Rahmenlisten mit Code & Label
- Extrafunktionen (z. B. Bodendichtung, Lüftungsgitter)

### Regeln
- Labels für Bauart-Bezeichnungen (`constructionLabels`).

Alle Eingaben werden sanitisiert; leere Listen fallen automatisch auf die mitgelieferten Default-Werte zurück.

## 🧰 Entwicklung & Hinweise
- Zentrale Datenbeschaffung erfolgt über `get_option` mit Fallback auf `default_data()`.
- `sanitize_data()` reinigt alle Admin-Eingaben, inklusive Listen, Media-URLs und Labels.
- Frontend-Assets werden nur geladen, wenn der Shortcode im Inhalt vorkommt.

## 📁 Projektstruktur
- `schmitke-door-configurator.php` – Haupt-Plugin-Datei mit Shortcode, Settings-Page und Sanitizing.
- `admin/` – Assets für den Admin-Bereich (Color-Picker, Mediathek-Integration, Styling).
- `public/` – Frontend-Assets des Konfigurators.

## 🆘 Support
Fragen oder Feature-Wünsche können im Projekt-Repository als Issue erfasst werden.
