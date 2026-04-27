# Konfigurator Settings v2

Alle neuen UI-Elemente, Optionen und Abhängigkeiten werden unter dem WordPress-Optionsschlüssel `schmitke_configurator_settings_v2` gespeichert. Die Struktur ist komplett JSON-basiert und umfasst genau einen Optionswert.

## Datenmodell

```jsonc
{
  "design": {
    "primaryColor": "#111111",
    "accentColor": "#f2f2f2",
    "textColor": "#111111",
    "borderColor": "#e7e7e7",
    "accordionToggleBg": "#111111",
    "accordionToggleIcon": "#ffffff",
    "accordionToggleBgHover": "#000000",
    "accordionToggleIconHover": "#ffffff",
    "fontFamily": "system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif",
    "buttonRadius": 10,
    "cardRadius": 14
  },
  "elements": [
    {
      "element_key": "material",
      "type": "single", // single|multi|measurements|upload
      "labels": { "de": "Material", "en": "Material" },
      "info": { "de": "", "en": "" },
      "visible_default": true,
      "required_default": false,
      "accordion_default_open": false,
      "order": 2,
      "allow_search": false, // falls null -> auto true bei >10 Optionen
      "ui_columns_desktop": 3,
      "ui_columns_tablet": 2,
      "ui_columns_mobile": 1
    }
  ],
  "options_by_element": {
    "material": [
      {
        "option_code": "pvc",
        "labels": { "de": "Kunststoff", "en": "uPVC" },
        "image_id": 0,
        "is_default": true,
        "price": null,
        "unit": null,
        "disabled": false
      }
    ]
  },
  "rules": [
    {
      "id": "rule_1",
      "name": "Raffstore nur mit Aluminium",
      "priority": 10,
      "when": {
        "type": "and",
        "conditions": [
          { "element_key": "material", "operator": "equals", "value": "aluminium" }
        ]
      },
      "then": {
        "filter_options": [
          { "element_key": "sonnenschutz", "allowed": ["raffstore"] }
        ],
        "disable_options": [
          { "element_key": "sonnenschutz", "codes": ["rollladen"], "reason": { "de": "Nur Raffstore", "en": "Venetian only" } }
        ],
        "show_elements": [],
        "hide_elements": [],
        "set_required": [],
        "unset_required": []
      }
    }
  ],
  "global_ui": {
    "sticky_summary_enabled": true,
    "accordion_enabled": true,
    "locale_mode": "wp_locale"
  }
}
```

## Seed-Daten
- Alle 29 geforderten Elemente sind im Default enthalten (z. B. Typ, Material, Verglasung, Maße & Anzahl, Ornamentglas, Schallschutzglas, Kundenfoto-Upload).
- Optionslisten enthalten initiale Beispiele (Codes + DE/EN Labels) und können frei ergänzt oder ersetzt werden.
- `allow_search` springt automatisch auf `true`, sobald eine Optionsliste mehr als 10 Einträge hat, falls kein expliziter Wert gesetzt wurde.

## Regeln
- Bedingungen unterstützen `equals`, `not_equals`, `in`, `not_in`, `contains`, `exists`.
- Aktionen erlauben `show_elements`, `hide_elements`, `filter_options`, `disable_options` (mit DE/EN Begründung), `set_required`, `unset_required`.
- Die Reihenfolge wird über `priority` gesteuert; niedrigere Werte laufen zuerst.
- Der Admin bietet dafür einen visuellen Builder:
  - Bedingungen: „Wenn Element“, „Operator“, „Wert“ (mit Option-Dropdown bei Single/Multi).
  - Aktionen: Multi-Selects für Elementlisten sowie wiederholbare Zeilen für `filter_options` und `disable_options`.
  - Intern bleibt die Speicherung vollständig kompatibel im bestehenden `rules`-JSON.

## Design-Tokens (neu)
- `accordionToggleBg`: Hintergrundfarbe des Accordion-Toggle-Buttons.
- `accordionToggleIcon`: Pfeilfarbe im Accordion-Toggle.
- `accordionToggleBgHover`: Hintergrundfarbe bei Hover/Focus.
- `accordionToggleIconHover`: Pfeilfarbe bei Hover/Focus.

### Praxisbeispiel: Produktspezifische Einschränkungen

Die gewünschten Regeln sind mit dem aktuellen Regelmodell umsetzbar (über `filter_options`).
Voraussetzung ist, dass es passende Elemente und Option-Codes gibt, z. B.:

- Element `typ` mit Optionen: `psk_fenster`, `hst`, `terrassentuer`
- Element `form` mit Option: `rechteckig`
- Element `fluegelzahl` mit Optionen: `zweiflueglig`, `dreiflueglig`
- Element `profil` mit Option: `veka_gw_500`

Beispiel-Regeln:

```jsonc
{
  "rules": [
    {
      "id": "rule_psk_fenster",
      "name": "PSK Fenster nur rechteckig + 2/3-flüglig",
      "priority": 10,
      "when": {
        "type": "and",
        "conditions": [
          { "element_key": "typ", "operator": "equals", "value": "psk_fenster" }
        ]
      },
      "then": {
        "filter_options": [
          { "element_key": "form", "allowed": ["rechteckig"] },
          { "element_key": "fluegelzahl", "allowed": ["zweiflueglig", "dreiflueglig"] }
        ]
      }
    },
    {
      "id": "rule_hst",
      "name": "HST nur VEKA GW 500 + rechteckig + 2/3-flüglig",
      "priority": 20,
      "when": {
        "type": "and",
        "conditions": [
          { "element_key": "typ", "operator": "equals", "value": "hst" }
        ]
      },
      "then": {
        "filter_options": [
          { "element_key": "profil", "allowed": ["veka_gw_500"] },
          { "element_key": "form", "allowed": ["rechteckig"] },
          { "element_key": "fluegelzahl", "allowed": ["zweiflueglig", "dreiflueglig"] }
        ]
      }
    },
    {
      "id": "rule_terrassentuer",
      "name": "Terrassentür nur rechteckig",
      "priority": 30,
      "when": {
        "type": "and",
        "conditions": [
          { "element_key": "typ", "operator": "equals", "value": "terrassentuer" }
        ]
      },
      "then": {
        "filter_options": [
          { "element_key": "form", "allowed": ["rechteckig"] }
        ]
      }
    }
  ]
}
```

Hinweise:
- Die Option-Codes in `allowed` müssen exakt zu den Codes in `options_by_element` passen.
- `filter_options` blendet nicht erlaubte Optionen aus; bestehende, ungültige Auswahlwerte werden im Frontend automatisch entfernt.
- Falls im Admin bereits andere Regeln auf dasselbe Element filtern, wird die Schnittmenge (Intersection) der erlaubten Optionen verwendet.

### Dieselben Regeln direkt in der GUI eingeben (ohne JSON-Fallback)

Wenn du die Regeln im Admin-Formular (Regel-Card) einträgst, sind für deinen Fall fast nur diese Felder wichtig:

- `ID`
- `Name`
- `Priorität`
- `Bedingungen` (bei dir jeweils nur 1 Bedingung: `Typ equals ...`)
- `filter_options (JSON)`

Die Felder `Elemente zeigen`, `Elemente verstecken`, `Pflicht setzen`, `Pflicht entfernen`, `disable_options (JSON)` können leer bleiben.

#### 1) Regel „PSK Fenster nur rechteckig + 2/3-flüglig“

- **ID**: `rule_psk_fenster`
- **Name**: `PSK Fenster nur rechteckig + 2/3-flüglig`
- **Priorität**: `10`
- **Bedingung**:
  - Element: `typ`
  - Operator: `equals`
  - Wert: `psk_fenster`
- **filter_options (JSON)**:

```json
[{"element_key":"form","allowed":["rechteckig"]},{"element_key":"fluegelzahl","allowed":["zweiflueglig","dreiflueglig"]}]
```

#### 2) Regel „HST nur VEKA GW 500 + rechteckig + 2/3-flüglig“

- **ID**: `rule_hst`
- **Name**: `HST nur VEKA GW 500 + rechteckig + 2/3-flüglig`
- **Priorität**: `20`
- **Bedingung**:
  - Element: `typ`
  - Operator: `equals`
  - Wert: `hst`
- **filter_options (JSON)**:

```json
[{"element_key":"profil","allowed":["veka_gw_500"]},{"element_key":"form","allowed":["rechteckig"]},{"element_key":"fluegelzahl","allowed":["zweiflueglig","dreiflueglig"]}]
```

#### 3) Regel „Terrassentür nur rechteckig“

- **ID**: `rule_terrassentuer`
- **Name**: `Terrassentür nur rechteckig`
- **Priorität**: `30`
- **Bedingung**:
  - Element: `typ`
  - Operator: `equals`
  - Wert: `terrassentuer`
- **filter_options (JSON)**:

```json
[{"element_key":"form","allowed":["rechteckig"]}]
```

#### Typische Fehler (wichtig)

- In `filter_options (JSON)` sind nur gültige JSON-Arrays erlaubt (mit `[` und `]`).
- `element_key` und Codes in `allowed` müssen exakt so heißen wie in deinen Optionen (z. B. `fluegelzahl` ≠ `flügelzahl`).
- Falls eine Option nicht auftaucht, zuerst prüfen, ob sie beim betroffenen Element wirklich mit exakt diesem Code existiert.
- Im Admin wird ungültiges JSON jetzt direkt rot markiert; erst bei gültigem JSON wird die Regel korrekt gespeichert.

## UI-Defaults
- `sticky_summary_enabled`: aktiviert die Sticky-Summary im Desktop-Layout.
- `accordion_enabled`: erlaubt einklappbare Elemente; Startzustand pro Element über `accordion_default_open`.
- `locale_mode: wp_locale`: nutzt automatisch die WordPress-Lokalisierung (DE/EN Labels/Infos).
