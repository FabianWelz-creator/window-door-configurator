<?php
if (!defined('ABSPATH')) exit;

/**
 * Centralized settings handler for the window/door configurator (v2 format).
 *
 * Stored under the option key {@see self::OPTION_KEY} with a single array
 * payload:
 * - design: shared color/typography tokens
 * - measurements: labels/placeholders for measurement fields
 * - elements: ordered list of element metadata
 * - options_by_element: map element_key => option list
 * - rules: conditional logic
 * - global_ui: shared UI toggles
 */
class Schmitke_Configurator_Settings_V2 {
    const OPTION_KEY = 'schmitke_configurator_settings_v2';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'maybe_migrate_legacy']);
        add_action('admin_init', [__CLASS__, 'register_option']);
    }

    public static function maybe_migrate_legacy() {
        $existing = get_option(self::OPTION_KEY, '__missing__');
        // Only migrate when the option is truly missing; an intentionally empty config should not be overwritten.
        if ($existing !== '__missing__') return;

        $legacyKeys = [
            'schmitke_configurator_settings',
            'schmitke_windows_configurator_data',
            'schmitke_doors_configurator_data_v21',
        ];

        foreach ($legacyKeys as $legacyKey) {
            $legacyData = get_option($legacyKey, null);
            if (empty($legacyData) || !is_array($legacyData)) continue;

            $mapped = self::migrate_from_legacy($legacyData);
            if (!empty($mapped)) {
                add_option(self::OPTION_KEY . '_legacy_backup', $legacyData);
                update_option(self::OPTION_KEY, $mapped);
                return;
            }
        }
    }

    private static function migrate_from_legacy($legacyData) {
        $defaults = self::default_settings();

        $elements = self::map_legacy_elements($legacyData['elements_meta'] ?? [], $defaults['elements']);
        $optionsByElement = self::sanitize_options_by_element(
            $legacyData['element_options'] ?? [],
            $elements,
            $defaults['options_by_element']
        );

        $rules = [];
        if (!empty($legacyData['rules']) && is_array($legacyData['rules'])) {
            $rules = self::sanitize_rules($legacyData['rules'], $defaults['rules']);
        }

        $globalUi = $defaults['global_ui'];
        if (!empty($legacyData['global_ui']) && is_array($legacyData['global_ui'])) {
            $globalUi = self::sanitize_global_ui($legacyData['global_ui'], $defaults['global_ui']);
        }

        return [
            'design' => self::sanitize_design($legacyData['design'] ?? [], $defaults['design']),
            'measurements' => self::sanitize_measurements($legacyData['measurements'] ?? [], $defaults['measurements']),
            'elements' => $elements,
            'options_by_element' => $optionsByElement,
            'rules' => $rules,
            'global_ui' => $globalUi,
        ];
    }

    private static function map_legacy_elements($legacyElements, $seed) {
        $mapped = [];
        if (is_array($legacyElements)) {
            foreach ($legacyElements as $entry) {
                if (!is_array($entry)) continue;
                $key = sanitize_key($entry['element_key'] ?? '');
                if ($key === '') continue;
                $labels = $entry['labels'] ?? $entry['label'] ?? [];
                $info = $entry['info'] ?? [];
                $mapped[] = [
                    'element_key' => $key,
                    'type' => in_array($entry['type'] ?? '', ['single','multi','measurements','upload'], true) ? $entry['type'] : 'single',
                    'labels' => [
                        'de' => sanitize_text_field($labels['de'] ?? ''),
                        'en' => sanitize_text_field($labels['en'] ?? ''),
                    ],
                    'info' => [
                        'de' => sanitize_text_field($info['de'] ?? ''),
                        'en' => sanitize_text_field($info['en'] ?? ''),
                    ],
                    'visible_default' => isset($entry['visible_default']) ? (bool)$entry['visible_default'] : true,
                    'required_default' => isset($entry['required_default']) ? (bool)$entry['required_default'] : false,
                    'accordion_default_open' => isset($entry['accordion_default_open']) ? (bool)$entry['accordion_default_open'] : false,
                    'order' => intval($entry['order'] ?? count($mapped) + 1),
                    'allow_search' => isset($entry['allow_search']) ? (bool)$entry['allow_search'] : false,
                    'ui_columns_desktop' => intval($entry['ui_columns_desktop'] ?? 3),
                    'ui_columns_tablet' => intval($entry['ui_columns_tablet'] ?? 2),
                    'ui_columns_mobile' => intval($entry['ui_columns_mobile'] ?? 1),
                ];
            }
        }

        if (empty($mapped)) return $seed;

        // Preserve ordering and merge any missing seeded elements at the end.
        $byKey = [];
        foreach ($mapped as $entry) {
            $byKey[$entry['element_key']] = $entry;
        }
        foreach ($seed as $entry) {
            if (!isset($byKey[$entry['element_key']])) {
                $byKey[$entry['element_key']] = $entry;
            }
        }

        usort($byKey, function($a, $b){
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return array_values($byKey);
    }

    public static function register_option() {
        register_setting('schmitke_configurator_settings_v2_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default' => self::default_settings(),
        ]);

        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, self::default_settings());
        }
    }

    public static function get_settings() {
        $raw = get_option(self::OPTION_KEY, []);
        return self::sanitize_settings($raw);
    }

    public static function sanitize_settings($input) {
        if (is_string($input)) {
            $decoded = json_decode(stripslashes($input), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
        $defaults = self::default_settings();
        $source = is_array($input) ? $input : [];

        $uidMap = [];
        $legacyKeyMap = [];
        $elements = self::sanitize_elements($source['elements'] ?? [], $defaults['elements'], $uidMap, $legacyKeyMap);
        $optionsByElement = self::sanitize_options_by_element(
            $source['options_by_element'] ?? [],
            $elements,
            $defaults['options_by_element'],
            $uidMap,
            $legacyKeyMap
        );
        $rules = self::sanitize_rules($source['rules'] ?? [], $defaults['rules']);
        $globalUi = self::sanitize_global_ui($source['global_ui'] ?? [], $defaults['global_ui']);

        $design = self::sanitize_design($source['design'] ?? [], $defaults['design']);
        $measurements = self::sanitize_measurements($source['measurements'] ?? [], $defaults['measurements']);

        return [
            'design' => $design,
            'measurements' => $measurements,
            'elements' => $elements,
            'options_by_element' => $optionsByElement,
            'rules' => $rules,
            'global_ui' => $globalUi,
        ];
    }

    private static function sanitize_global_ui($settings, $defaults) {
        $source = is_array($settings) ? $settings : [];
        $allowedLocaleModes = ['wp_locale', 'force_de', 'force_en'];

        $stickySummary = isset($source['sticky_summary_enabled'])
            ? (bool)$source['sticky_summary_enabled']
            : ($defaults['sticky_summary_enabled'] ?? true);

        $accordion = isset($source['accordion_enabled'])
            ? (bool)$source['accordion_enabled']
            : ($defaults['accordion_enabled'] ?? true);

        $localeMode = isset($source['locale_mode']) ? sanitize_key($source['locale_mode']) : ($defaults['locale_mode'] ?? 'wp_locale');
        if (!in_array($localeMode, $allowedLocaleModes, true)) {
            $localeMode = $defaults['locale_mode'] ?? 'wp_locale';
        }

        return [
            'sticky_summary_enabled' => $stickySummary,
            'accordion_enabled' => $accordion,
            'locale_mode' => $localeMode,
        ];
    }

    private static function sanitize_design($settings, $defaults) {
        $src = is_array($settings) ? $settings : [];
        return [
            'primaryColor' => sanitize_hex_color($src['primaryColor'] ?? '') ?: ($defaults['primaryColor'] ?? '#111111'),
            'accentColor' => sanitize_hex_color($src['accentColor'] ?? '') ?: ($defaults['accentColor'] ?? '#f2f2f2'),
            'textColor' => sanitize_hex_color($src['textColor'] ?? '') ?: ($defaults['textColor'] ?? '#111111'),
            'borderColor' => sanitize_hex_color($src['borderColor'] ?? '') ?: ($defaults['borderColor'] ?? '#e7e7e7'),
            'fontFamily' => sanitize_text_field($src['fontFamily'] ?? ($defaults['fontFamily'] ?? '')),
            'buttonRadius' => max(0, intval($src['buttonRadius'] ?? ($defaults['buttonRadius'] ?? 0))),
            'cardRadius' => max(0, intval($src['cardRadius'] ?? ($defaults['cardRadius'] ?? 0))),
        ];
    }

    private static function sanitize_measurements($settings, $defaults) {
        $src = is_array($settings) ? $settings : [];
        $labels = is_array($src['labels'] ?? null) ? $src['labels'] : [];

        $sanitizeLabelSet = function($key, $fallback) use ($labels) {
            $val = is_array($labels[$key] ?? null) ? $labels[$key] : [];
            return [
                'de' => sanitize_text_field($val['de'] ?? ($fallback['de'] ?? '')),
                'en' => sanitize_text_field($val['en'] ?? ($fallback['en'] ?? '')),
            ];
        };

        $defaultLabels = $defaults['labels'] ?? [];

        return [
            'labels' => [
                'width' => $sanitizeLabelSet('width', $defaultLabels['width'] ?? []),
                'height' => $sanitizeLabelSet('height', $defaultLabels['height'] ?? []),
                'quantity' => $sanitizeLabelSet('quantity', $defaultLabels['quantity'] ?? []),
                'note' => $sanitizeLabelSet('note', $defaultLabels['note'] ?? []),
                'upload' => $sanitizeLabelSet('upload', $defaultLabels['upload'] ?? []),
            ],
        ];
    }

    public static function default_settings() {
        return [
            'design' => self::default_design(),
            'measurements' => self::default_measurements(),
            'elements' => self::seed_elements(),
            'options_by_element' => self::seed_options(),
            'rules' => [],
            'global_ui' => [
                'sticky_summary_enabled' => true,
                'accordion_enabled' => true,
                'locale_mode' => 'wp_locale',
            ],
        ];
    }

    private static function default_design() {
        return [
            'primaryColor' => '#111111',
            'accentColor' => '#f2f2f2',
            'textColor' => '#111111',
            'borderColor' => '#e7e7e7',
            'fontFamily' => 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
            'buttonRadius' => 10,
            'cardRadius' => 14,
        ];
    }

    private static function default_measurements() {
        return [
            'labels' => [
                'width' => ['de' => 'Breite (mm)', 'en' => 'Width (mm)'],
                'height' => ['de' => 'Höhe (mm)', 'en' => 'Height (mm)'],
                'quantity' => ['de' => 'Anzahl', 'en' => 'Quantity'],
                'note' => ['de' => 'Notiz', 'en' => 'Note'],
                'upload' => ['de' => 'Upload', 'en' => 'Upload'],
            ],
        ];
    }

    private static function seed_elements() {
        $order = 1;
        $e = function($key, $type, $labelDe, $labelEn, $visible = true, $required = false, $accordionOpen = false, $allowSearch = false) use (&$order) {
            return [
                'element_key' => $key,
                'type' => $type,
                'labels' => ['de' => $labelDe, 'en' => $labelEn],
                'info' => ['de' => '', 'en' => ''],
                'visible_default' => $visible,
                'required_default' => $required,
                'accordion_default_open' => $accordionOpen,
                'order' => $order++,
                'allow_search' => $allowSearch,
                'ui_columns_desktop' => 3,
                'ui_columns_tablet' => 2,
                'ui_columns_mobile' => 1,
            ];
        };

        return [
            $e('typ', 'single', 'Typ', 'Type'),
            $e('material', 'single', 'Material', 'Material'),
            $e('verglasung', 'single', 'Verglasung', 'Glazing'),
            $e('profil', 'single', 'Profil', 'Profile'),
            $e('farben_innen_aussen', 'single', 'Farben innen/außen', 'Interior/Exterior colors', true, false, false, true),
            $e('form', 'single', 'Form', 'Shape'),
            $e('fluegel', 'single', 'Flügel', 'Sashes'),
            $e('bauform', 'single', 'Bauform', 'Construction'),
            $e('sonnenschutz', 'single', 'Sonnenschutz', 'Sun protection'),
            $e('farbe_sonnenschutz', 'single', 'Farbe (Sonnenschutz)', 'Sun protection color'),
            $e('kastenform', 'single', 'Kastenform', 'Box shape'),
            $e('lamellen', 'single', 'Lamellen', 'Slats'),
            $e('insektenschutz', 'single', 'Insektenschutz', 'Insect protection'),
            $e('farben_zusatz', 'single', 'Farben Zusatzkomponenten', 'Accessory colors'),
            $e('antrieb', 'single', 'Antrieb', 'Drive'),
            $e('bedienseite', 'single', 'Bedienseite', 'Control side'),
            $e('masse_anzahl', 'measurements', 'Maße & Anzahl', 'Dimensions & Quantity', true, true, true),
            $e('sprossen', 'single', 'Sprossen', 'Muntins'),
            $e('sprossenaufteilung', 'single', 'Sprossenaufteilung', 'Muntin layout'),
            $e('zusatzausstattung', 'multi', 'Zusatzausstattung', 'Add-ons'),
            $e('ornamentglas', 'multi', 'Ornamentglas', 'Ornamental glass'),
            $e('schallschutzglas', 'multi', 'Schallschutzglas', 'Sound insulation glass'),
            $e('randverband', 'single', 'Randverband', 'Spacer'),
            $e('sicherheitsbeschlag', 'single', 'Sicherheitsbeschlag', 'Security fittings'),
            $e('fenstergriff', 'single', 'Fenstergriff', 'Handle'),
            $e('fuehrungsschienen', 'single', 'Führungsschienen/Zubehör', 'Guide rails/Accessories'),
            $e('fensterbank', 'single', 'Fensterbank', 'Windowsill'),
            $e('rolladenkasten', 'single', 'Vorhandener Rolladenkasten', 'Existing roller shutter box'),
            $e('kundenfoto', 'upload', 'Kundenfoto-Upload', 'Customer photo upload'),
        ];
    }

    private static function seed_options() {
        return [
            'typ' => self::options([
                ['code' => 'fenster', 'de' => 'Fenster', 'en' => 'Window', 'default' => true],
                ['code' => 'tuere', 'de' => 'Tür', 'en' => 'Door'],
                ['code' => 'haustuer', 'de' => 'Haustür', 'en' => 'Front door'],
            ]),
            'material' => self::options([
                ['code' => 'pvc', 'de' => 'Kunststoff', 'en' => 'uPVC', 'default' => true],
                ['code' => 'aluminium', 'de' => 'Aluminium', 'en' => 'Aluminium'],
                ['code' => 'holz', 'de' => 'Holz', 'en' => 'Wood'],
                ['code' => 'holz_aluminium', 'de' => 'Holz/Alu', 'en' => 'Wood/Alu'],
            ]),
            'verglasung' => self::options([
                ['code' => '2fach', 'de' => '2-fach', 'en' => 'Double glazed', 'default' => true],
                ['code' => '3fach', 'de' => '3-fach', 'en' => 'Triple glazed'],
            ]),
            'profil' => self::options([
                ['code' => 'slim', 'de' => 'Slim', 'en' => 'Slim', 'default' => true],
                ['code' => 'classic', 'de' => 'Classic', 'en' => 'Classic'],
            ]),
            'farben_innen_aussen' => self::options([
                ['code' => 'ral9016', 'de' => 'Weiß (RAL 9016)', 'en' => 'White (RAL 9016)', 'default' => true],
                ['code' => 'ral7016', 'de' => 'Anthrazit (RAL 7016)', 'en' => 'Anthracite (RAL 7016)'],
                ['code' => 'golden_oak', 'de' => 'Golden Oak', 'en' => 'Golden Oak'],
                ['code' => 'schwarzbraun', 'de' => 'Schwarzbraun', 'en' => 'Black-brown'],
            ]),
            'form' => self::options([
                ['code' => 'rechteck', 'de' => 'Rechteckig', 'en' => 'Rectangular', 'default' => true],
                ['code' => 'rundbogen', 'de' => 'Rundbogen', 'en' => 'Round arch'],
                ['code' => 'schraeg', 'de' => 'Schräg', 'en' => 'Angled'],
            ]),
            'fluegel' => self::options([
                ['code' => '1fluegel', 'de' => '1-flügelig', 'en' => '1 sash', 'default' => true],
                ['code' => '2fluegel', 'de' => '2-flügelig', 'en' => '2 sashes'],
                ['code' => '3fluegel', 'de' => '3-flügelig', 'en' => '3 sashes'],
            ]),
            'bauform' => self::options([
                ['code' => 'drehkipp', 'de' => 'Dreh-Kipp', 'en' => 'Tilt and turn', 'default' => true],
                ['code' => 'fest', 'de' => 'Festverglast', 'en' => 'Fixed'],
                ['code' => 'psk', 'de' => 'Parallel-Schiebe-Kipp', 'en' => 'PSK'],
            ]),
            'sonnenschutz' => self::options([
                ['code' => 'rollladen', 'de' => 'Rollladen', 'en' => 'Roller shutter', 'default' => true],
                ['code' => 'raffstore', 'de' => 'Raffstore', 'en' => 'Venetian blinds'],
                ['code' => 'keiner', 'de' => 'Kein Sonnenschutz', 'en' => 'None'],
            ]),
            'farbe_sonnenschutz' => self::options([
                ['code' => 'sonnenschutz_weiss', 'de' => 'Weiß', 'en' => 'White', 'default' => true],
                ['code' => 'sonnenschutz_grau', 'de' => 'Grau', 'en' => 'Grey'],
                ['code' => 'sonnenschutz_anthrazit', 'de' => 'Anthrazit', 'en' => 'Anthracite'],
            ]),
            'kastenform' => self::options([
                ['code' => 'eckig', 'de' => 'Eckig', 'en' => 'Square', 'default' => true],
                ['code' => 'rund', 'de' => 'Rund', 'en' => 'Round'],
            ]),
            'lamellen' => self::options([
                ['code' => '70mm', 'de' => '70mm', 'en' => '70mm', 'default' => true],
                ['code' => '90mm', 'de' => '90mm', 'en' => '90mm'],
            ]),
            'insektenschutz' => self::options([
                ['code' => 'kein', 'de' => 'Kein Insektenschutz', 'en' => 'None', 'default' => true],
                ['code' => 'drehrahmen', 'de' => 'Drehrahmen', 'en' => 'Swing frame'],
                ['code' => 'schiebeanlage', 'de' => 'Schiebeanlage', 'en' => 'Sliding system'],
            ]),
            'farben_zusatz' => self::options([
                ['code' => 'zusatz_weiss', 'de' => 'Weiß', 'en' => 'White', 'default' => true],
                ['code' => 'zusatz_alu', 'de' => 'Aluminium', 'en' => 'Aluminium'],
                ['code' => 'zusatz_anthrazit', 'de' => 'Anthrazit', 'en' => 'Anthracite'],
            ]),
            'antrieb' => self::options([
                ['code' => 'manuell', 'de' => 'Manuell', 'en' => 'Manual', 'default' => true],
                ['code' => 'motor', 'de' => 'Motorisiert', 'en' => 'Motorized'],
            ]),
            'bedienseite' => self::options([
                ['code' => 'links', 'de' => 'Links', 'en' => 'Left', 'default' => true],
                ['code' => 'rechts', 'de' => 'Rechts', 'en' => 'Right'],
            ]),
            'masse_anzahl' => [],
            'sprossen' => self::options([
                ['code' => 'keine', 'de' => 'Keine', 'en' => 'None', 'default' => true],
                ['code' => 'glasteilend', 'de' => 'Glasteilend', 'en' => 'Within glass'],
                ['code' => 'augesetzt', 'de' => 'Aufgesetzt', 'en' => 'Applied'],
            ]),
            'sprossenaufteilung' => self::options([
                ['code' => 'symmetrisch', 'de' => 'Symmetrisch', 'en' => 'Symmetric', 'default' => true],
                ['code' => 'asymmetrisch', 'de' => 'Asymmetrisch', 'en' => 'Asymmetric'],
            ]),
            'zusatzausstattung' => self::options([
                ['code' => 'falzluftung', 'de' => 'Falzlüftung', 'en' => 'Ventilation'],
                ['code' => 'abschliessbar', 'de' => 'Abschließbarer Griff', 'en' => 'Lockable handle'],
                ['code' => 'kindersicherung', 'de' => 'Kindersicherung', 'en' => 'Child safety'],
            ]),
            'ornamentglas' => self::options([
                ['code' => 'chinchilla', 'de' => 'Chinchilla', 'en' => 'Chinchilla'],
                ['code' => 'satinato', 'de' => 'Satinato', 'en' => 'Satinato'],
                ['code' => 'mastercarre', 'de' => 'Master-Carré', 'en' => 'Master-Carré'],
            ]),
            'schallschutzglas' => self::options([
                ['code' => 'klasse2', 'de' => 'Schallschutzklasse 2', 'en' => 'Class 2'],
                ['code' => 'klasse3', 'de' => 'Schallschutzklasse 3', 'en' => 'Class 3'],
            ]),
            'randverband' => self::options([
                ['code' => 'warm_edge', 'de' => 'Warme Kante', 'en' => 'Warm edge', 'default' => true],
                ['code' => 'alu', 'de' => 'Alu', 'en' => 'Aluminium'],
            ]),
            'sicherheitsbeschlag' => self::options([
                ['code' => 'standard', 'de' => 'Standard', 'en' => 'Standard', 'default' => true],
                ['code' => 'rc2', 'de' => 'RC2-Vorbereitung', 'en' => 'RC2 preparation'],
            ]),
            'fenstergriff' => self::options([
                ['code' => 'alu', 'de' => 'Aluminium', 'en' => 'Aluminium', 'default' => true],
                ['code' => 'edelstahl', 'de' => 'Edelstahl', 'en' => 'Stainless steel'],
                ['code' => 'abschliessbar', 'de' => 'Abschließbar', 'en' => 'Lockable'],
            ]),
            'fuehrungsschienen' => self::options([
                ['code' => 'falz', 'de' => 'Falzführung', 'en' => 'Rebate guide', 'default' => true],
                ['code' => 'putz', 'de' => 'Putzschiene', 'en' => 'Render rail'],
            ]),
            'fensterbank' => self::options([
                ['code' => 'innen', 'de' => 'Innenfensterbank', 'en' => 'Interior sill', 'default' => true],
                ['code' => 'aussen', 'de' => 'Außenfensterbank', 'en' => 'Exterior sill'],
                ['code' => 'keine', 'de' => 'Keine', 'en' => 'None'],
            ]),
            'rolladenkasten' => self::options([
                ['code' => 'vorhanden', 'de' => 'Vorhanden', 'en' => 'Existing', 'default' => true],
                ['code' => 'nicht_vorhanden', 'de' => 'Nicht vorhanden', 'en' => 'Not available'],
            ]),
            'kundenfoto' => [],
        ];
    }

    private static function options($list) {
        $options = [];
        foreach ($list as $index => $item) {
            $code = sanitize_key($item['code'] ?? '');
            if ($code === '') continue;
            $options[] = [
                'option_code' => $code,
                'labels' => [
                    'de' => sanitize_text_field($item['de'] ?? ''),
                    'en' => sanitize_text_field($item['en'] ?? ''),
                ],
                'image_id' => 0,
                'is_default' => !empty($item['default']) || ($index === 0 && !array_key_exists('default', $item)),
                'price' => null,
                'unit' => null,
                'disabled' => false,
            ];
        }
        return $options;
    }

    private static function sanitize_elements($elements, $defaults, &$uidMap = [], &$legacyKeyMap = []) {
        $allowedTypes = ['single', 'multi', 'measurements', 'upload'];
        $list = [];
        $source = is_array($elements) ? $elements : [];
        $generatedIndex = 1;

        foreach ($source as $entry) {
            if (!is_array($entry)) continue;
            $uid = sanitize_text_field($entry['__uid'] ?? '');
            if ($uid === '') {
                $uid = 'el_' . uniqid('', true);
            }
            $key = sanitize_key($entry['element_key'] ?? '');
            if ($key === '') {
                $key = 'element_' . $generatedIndex++;
            }
            $uidMap[$uid] = $key;
            $legacyKeyMap[$key] = $key;
            $list[] = [
                '__uid' => $uid,
                'element_key' => $key,
                'type' => in_array($entry['type'] ?? '', $allowedTypes, true) ? $entry['type'] : 'single',
                'labels' => [
                    'de' => sanitize_text_field($entry['labels']['de'] ?? ''),
                    'en' => sanitize_text_field($entry['labels']['en'] ?? ''),
                ],
                'info' => [
                    'de' => sanitize_text_field($entry['info']['de'] ?? ''),
                    'en' => sanitize_text_field($entry['info']['en'] ?? ''),
                ],
                'visible_default' => isset($entry['visible_default']) ? (bool)$entry['visible_default'] : true,
                'required_default' => isset($entry['required_default']) ? (bool)$entry['required_default'] : false,
                'accordion_default_open' => isset($entry['accordion_default_open']) ? (bool)$entry['accordion_default_open'] : false,
                'order' => isset($entry['order']) ? intval($entry['order']) : 0,
                'allow_search' => isset($entry['allow_search']) ? (bool)$entry['allow_search'] : null,
                'ui_columns_desktop' => isset($entry['ui_columns_desktop']) ? intval($entry['ui_columns_desktop']) : 3,
                'ui_columns_tablet' => isset($entry['ui_columns_tablet']) ? intval($entry['ui_columns_tablet']) : 2,
                'ui_columns_mobile' => isset($entry['ui_columns_mobile']) ? intval($entry['ui_columns_mobile']) : 1,
            ];
        }

        if (empty($list)) {
            foreach ($defaults as $entry) {
                $key = sanitize_key($entry['element_key'] ?? '');
                $uid = 'seed_' . ($key ?: uniqid('', true));
                $uidMap[$uid] = $key;
                $legacyKeyMap[$key] = $key;
                $list[] = array_merge(['__uid' => $uid], $entry);
            }
        }

        usort($list, function($a, $b){
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return array_values($list);
    }

    private static function sanitize_options_by_element($input, $elements, $defaults, $uidMap = [], $legacyKeyMap = []) {
        $sanitized = [];
        $source = is_array($input) ? $input : [];
        $elementKeys = array_map(function($el){ return $el['element_key']; }, $elements);

        foreach ($source as $elementKey => $options) {
            $key = sanitize_key($elementKey);
            $canonical = null;
            if (isset($uidMap[$elementKey])) {
                $canonical = $uidMap[$elementKey];
            } elseif ($key !== '' && isset($legacyKeyMap[$key])) {
                $canonical = $legacyKeyMap[$key];
            } elseif ($key !== '') {
                $canonical = $key;
            }
            if ($canonical === null) continue;
            $sanitized[$canonical] = self::sanitize_option_entries($options);
        }

        foreach ($elementKeys as $key) {
            if (!isset($sanitized[$key]) && isset($defaults[$key])) {
                $sanitized[$key] = self::sanitize_option_entries($defaults[$key]);
            }
        }

        foreach ($sanitized as $key => &$list) {
            $explicitSearch = null;
            foreach ($elements as $el) {
                if ($el['element_key'] === $key) {
                    $explicitSearch = $el['allow_search'];
                    break;
                }
            }
            if ($explicitSearch === null && count($list) > 10) {
                $explicitSearch = true;
            }
            if ($explicitSearch !== null) {
                foreach ($elements as &$el) {
                    if ($el['element_key'] === $key) {
                        $el['allow_search'] = (bool)$explicitSearch;
                    }
                }
                unset($el);
            }
        }
        unset($list);

        return $sanitized;
    }

    private static function sanitize_option_entries($options) {
        $result = [];
        if (!is_array($options)) return $result;

        foreach ($options as $opt) {
            if (!is_array($opt)) continue;
            $code = sanitize_key($opt['option_code'] ?? '');
            if ($code === '') continue;
            $result[] = [
                'option_code' => $code,
                'labels' => [
                    'de' => sanitize_text_field($opt['labels']['de'] ?? ''),
                    'en' => sanitize_text_field($opt['labels']['en'] ?? ''),
                ],
                'image_id' => intval($opt['image_id'] ?? 0),
                'is_default' => isset($opt['is_default']) ? (bool)$opt['is_default'] : false,
                'price' => isset($opt['price']) ? $opt['price'] : null,
                'unit' => isset($opt['unit']) ? sanitize_text_field($opt['unit']) : null,
                'disabled' => isset($opt['disabled']) ? (bool)$opt['disabled'] : false,
            ];
        }

        return $result;
    }

    private static function sanitize_rules($rules, $defaults) {
        $result = [];
        $source = is_array($rules) ? $rules : [];
        foreach ($source as $rule) {
            if (!is_array($rule)) continue;
            $result[] = [
                'id' => sanitize_key($rule['id'] ?? ''),
                'name' => sanitize_text_field($rule['name'] ?? ''),
                'priority' => isset($rule['priority']) ? intval($rule['priority']) : 0,
                'when' => self::sanitize_conditions_group($rule['when'] ?? []),
                'then' => self::sanitize_rule_actions($rule['then'] ?? []),
            ];
        }
        return $result ?: $defaults;
    }

    private static function sanitize_conditions_group($group) {
        $allowedOperators = ['equals', 'not_equals', 'in', 'not_in', 'contains', 'exists'];
        $conditions = [];
        if (isset($group['conditions']) && is_array($group['conditions'])) {
            foreach ($group['conditions'] as $cond) {
                if (!is_array($cond)) continue;
                $op = $cond['operator'] ?? '';
                if (!in_array($op, $allowedOperators, true)) continue;
                $conditions[] = [
                    'element_key' => sanitize_key($cond['element_key'] ?? ''),
                    'operator' => $op,
                    'value' => $cond['value'] ?? null,
                ];
            }
        }

        return [
            'type' => isset($group['type']) && in_array($group['type'], ['and', 'or'], true) ? $group['type'] : 'and',
            'conditions' => $conditions,
        ];
    }

    private static function sanitize_rule_actions($actions) {
        $showElements = isset($actions['show_elements']) && is_array($actions['show_elements']) ? array_map('sanitize_key', $actions['show_elements']) : [];
        $hideElements = isset($actions['hide_elements']) && is_array($actions['hide_elements']) ? array_map('sanitize_key', $actions['hide_elements']) : [];

        $filterOptions = [];
        if (!empty($actions['filter_options']) && is_array($actions['filter_options'])) {
            foreach ($actions['filter_options'] as $entry) {
                if (!is_array($entry)) continue;
                $filterOptions[] = [
                    'element_key' => sanitize_key($entry['element_key'] ?? ''),
                    'allowed' => array_values(array_filter(array_map('sanitize_key', $entry['allowed'] ?? []))),
                ];
            }
        }

        $disableOptions = [];
        if (!empty($actions['disable_options']) && is_array($actions['disable_options'])) {
            foreach ($actions['disable_options'] as $entry) {
                if (!is_array($entry)) continue;
                $disableOptions[] = [
                    'element_key' => sanitize_key($entry['element_key'] ?? ''),
                    'codes' => array_values(array_filter(array_map('sanitize_key', $entry['codes'] ?? []))),
                    'reason' => [
                        'de' => sanitize_text_field($entry['reason']['de'] ?? ''),
                        'en' => sanitize_text_field($entry['reason']['en'] ?? ''),
                    ],
                ];
            }
        }

        return [
            'show_elements' => $showElements,
            'hide_elements' => $hideElements,
            'filter_options' => $filterOptions,
            'disable_options' => $disableOptions,
            'set_required' => array_values(array_filter(array_map('sanitize_key', $actions['set_required'] ?? []))),
            'unset_required' => array_values(array_filter(array_map('sanitize_key', $actions['unset_required'] ?? []))),
        ];
    }
}

Schmitke_Configurator_Settings_V2::init();

