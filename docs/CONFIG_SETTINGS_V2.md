# Konfigurator Settings v2

Alle neuen UI-Elemente, Optionen und Abhängigkeiten werden unter dem WordPress-Optionsschlüssel `schmitke_configurator_settings_v2` gespeichert. Die Struktur ist komplett JSON-basiert und umfasst genau einen Optionswert.

## Datenmodell

```jsonc
{
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

## UI-Defaults
- `sticky_summary_enabled`: aktiviert die Sticky-Summary im Desktop-Layout.
- `accordion_enabled`: erlaubt einklappbare Elemente; Startzustand pro Element über `accordion_default_open`.
- `locale_mode: wp_locale`: nutzt automatisch die WordPress-Lokalisierung (DE/EN Labels/Infos).

