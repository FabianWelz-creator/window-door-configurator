<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalizes and sanitizes configurator settings for both door and window contexts.
 *
 * Legacy storage:
 * - Doors: option key "schmitke_doors_configurator_data_v21" with email, design,
 *   models (family/name/finish defaults), DIN sizes, frames/extras (code+label)
 *   and rules[constructionLabels].
 * - Windows: option key "schmitke_windows_configurator_data" with email, design,
 *   models (family/name + defaults), materials/frames/glass/extras (code+label)
 *   plus grouped "options" entries (key/title/subtitle/description + imageId).
 *
 * Legacy option keys remain untouched, the normalizer only augments data with
 * "elements_meta" and "element_options" so new consumers have a consistent
 * structure without breaking old saves.
 */
class Schmitke_Config_Normalizer {
    private static $order_cache = null;

    public static function default_elements_meta($type) {
        $order = 1;
        $defaults = [
            'doors' => [
                self::meta_entry('model', 'Türmodell', 'Door model', $order++),
                self::meta_entry('dimensions', 'Maße', 'Dimensions', $order++),
                self::meta_entry('construction', 'Bauart', 'Construction', $order++),
                self::meta_entry('la', 'Lichtausschnitt', 'Glazing cut-out', $order++),
                self::meta_entry('frame', 'Zarge', 'Frame', $order++),
                self::meta_entry('direction', 'Anschlag', 'Hinge direction', $order++),
                self::meta_entry('leafs', 'Flügel', 'Leafs', $order++),
                self::meta_entry('extras', 'Extras', 'Extras', $order++),
                self::meta_entry('customer_photo', 'Kundenfoto-Upload', 'Customer photo upload', $order++),
            ],
            'windows' => [
                self::meta_entry('model', 'Fenstermodell', 'Window model', $order++),
                self::meta_entry('type', 'Typ', 'Type', $order++),
                self::meta_entry('manufacturer', 'Hersteller', 'Manufacturer', $order++),
                self::meta_entry('profile', 'Profil', 'Profile', $order++),
                self::meta_entry('form', 'Form', 'Shape', $order++),
                self::meta_entry('sashes', 'Flügel', 'Sashes', $order++),
                self::meta_entry('opening', 'Öffnung', 'Opening', $order++),
                self::meta_entry('colorExterior', 'Außenfarbe', 'Exterior color', $order++),
                self::meta_entry('colorInterior', 'Innenfarbe', 'Interior color', $order++),
                self::meta_entry('material', 'Material', 'Material', $order++),
                self::meta_entry('frame', 'Rahmen', 'Frame', $order++),
                self::meta_entry('glass', 'Glas', 'Glass', $order++),
                self::meta_entry('extras', 'Extras', 'Extras', $order++),
            ],
        ];

        return $defaults[$type] ?? [];
    }

    public static function sanitize_elements_meta($input, $defaults = []) {
        $clean = [];
        $source = is_array($input) ? $input : [];
        foreach ($source as $entry) {
            if (!is_array($entry)) continue;
            $sanitized = self::sanitize_meta_entry($entry);
            if ($sanitized) $clean[] = $sanitized;
        }

        if (empty($clean) && !empty($defaults)) {
            foreach ($defaults as $entry) {
                $sanitized = self::sanitize_meta_entry($entry);
                if ($sanitized) $clean[] = $sanitized;
            }
        }

        return self::apply_ordering_to_meta($clean);
    }

    public static function sanitize_element_options($input, $type, $legacySource = []) {
        $clean = [];
        if (is_array($input)) {
            foreach ($input as $elementKey => $options) {
                $clean[$elementKey] = self::sanitize_option_list($options);
            }
        }

        $legacy = self::map_legacy_options($type, $legacySource);
        foreach ($legacy as $elementKey => $options) {
            if (empty($clean[$elementKey])) {
                $clean[$elementKey] = $options;
            }
        }

        return $clean;
    }

    public static function get_configurator_config($raw, $type, $defaults = []) {
        $base = is_array($raw) ? $raw : [];
        $result = is_array($defaults) ? array_merge($defaults, $base) : $base;

        $result['elements_meta'] = self::sanitize_elements_meta(
            $base['elements_meta'] ?? [],
            self::default_elements_meta($type)
        );

        $result['element_options'] = self::sanitize_element_options(
            $base['element_options'] ?? [],
            $type,
            $result
        );

        return $result;
    }

    private static function meta_entry($key, $labelDe, $labelEn, $order) {
        return [
            'element_key' => $key,
            'label' => ['de' => $labelDe, 'en' => $labelEn],
            'info' => ['de' => '', 'en' => ''],
            'visible_default' => true,
            'required_default' => false,
            'required_rules' => [],
            'accordion_default_open' => false,
            'order' => $order,
        ];
    }

    private static function sanitize_meta_entry($entry) {
        $key = sanitize_key($entry['element_key'] ?? '');
        if ($key === '') return null;
        $label = is_array($entry['label'] ?? null) ? $entry['label'] : [];
        $info = is_array($entry['info'] ?? null) ? $entry['info'] : [];
        return [
            'element_key' => $key,
            'label' => [
                'de' => sanitize_text_field($label['de'] ?? ''),
                'en' => sanitize_text_field($label['en'] ?? ''),
            ],
            'info' => [
                'de' => sanitize_text_field($info['de'] ?? ''),
                'en' => sanitize_text_field($info['en'] ?? ''),
            ],
            'visible_default' => isset($entry['visible_default']) ? (bool)$entry['visible_default'] : true,
            'required_default' => isset($entry['required_default']) ? (bool)$entry['required_default'] : false,
            'required_rules' => is_array($entry['required_rules'] ?? null) ? $entry['required_rules'] : [],
            'accordion_default_open' => isset($entry['accordion_default_open']) ? (bool)$entry['accordion_default_open'] : false,
            'order' => isset($entry['order']) ? intval($entry['order']) : 0,
        ];
    }

    private static function apply_ordering_to_meta($meta) {
        if (empty($meta)) return $meta;

        $orderList = self::get_element_order();
        $orderMap = [];
        foreach ($orderList as $idx => $elementKey) {
            $sanitizedKey = sanitize_key($elementKey);
            if ($sanitizedKey === '') continue;
            $orderMap[$sanitizedKey] = $idx + 1;
        }

        $normalized = [];
        foreach ($meta as $idx => $entry) {
            $key = $entry['element_key'] ?? '';
            $explicitOrder = isset($entry['order']) ? intval($entry['order']) : 0;
            $derivedOrder = $orderMap[$key] ?? 0;
            $entry['order'] = $explicitOrder ?: ($derivedOrder ?: (1000 + $idx));
            $entry['__orig_index'] = $idx;
            $normalized[] = $entry;
        }

        usort($normalized, function($a, $b) {
            if ($a['order'] === $b['order']) {
                return $a['__orig_index'] <=> $b['__orig_index'];
            }
            return $a['order'] <=> $b['order'];
        });

        foreach ($normalized as &$entry) {
            unset($entry['__orig_index']);
        }
        unset($entry);

        return $normalized;
    }

    private static function get_element_order() {
        if (self::$order_cache !== null) return self::$order_cache;

        $list = include __DIR__ . '/config-order.php';
        if (!is_array($list)) $list = [];

        return self::$order_cache = array_values(array_filter($list, function($v){
            return is_string($v) && sanitize_key($v) !== '';
        }));
    }

    private static function sanitize_option_list($options) {
        $clean = [];
        if (!is_array($options)) return $clean;
        foreach ($options as $opt) {
            if (!is_array($opt)) continue;
            $clean[] = self::sanitize_option_entry($opt);
        }
        return $clean;
    }

    private static function sanitize_option_entry($opt) {
        $label = is_array($opt['label'] ?? null) ? $opt['label'] : [];
        $info = is_array($opt['info'] ?? null) ? $opt['info'] : [];
        return [
            'option_code' => sanitize_key($opt['option_code'] ?? ''),
            'label' => [
                'de' => sanitize_text_field($label['de'] ?? ''),
                'en' => sanitize_text_field($label['en'] ?? ''),
            ],
            'info' => [
                'de' => sanitize_text_field($info['de'] ?? ''),
                'en' => sanitize_text_field($info['en'] ?? ''),
            ],
            'image_id' => intval($opt['image_id'] ?? 0),
            'is_default' => isset($opt['is_default']) ? (bool)$opt['is_default'] : false,
            'price' => $opt['price'] ?? null,
            'unit' => $opt['unit'] ?? null,
        ];
    }

    private static function map_legacy_options($type, $source) {
        if ($type === 'windows') {
            return self::map_windows_legacy_options($source);
        }
        return self::map_door_legacy_options($source);
    }

    private static function map_door_legacy_options($source) {
        $options = [];
        $options['frame'] = self::map_code_label_options($source['frames'] ?? []);
        $options['extras'] = self::map_code_label_options($source['extras'] ?? []);
        $options['model'] = self::map_model_options($source['models'] ?? []);
        $options['la'] = self::map_string_options(self::collect_la_options($source['models'] ?? []));
        $options['construction'] = self::map_construction_labels($source['rules']['constructionLabels'] ?? []);
        return $options;
    }

    private static function map_windows_legacy_options($source) {
        $options = [];
        $options['material'] = self::map_code_label_options($source['materials'] ?? []);
        $options['frame'] = self::map_code_label_options($source['frames'] ?? []);
        $options['glass'] = self::map_code_label_options($source['glass'] ?? []);
        $options['extras'] = self::map_code_label_options($source['extras'] ?? []);
        $options['model'] = self::map_model_options($source['models'] ?? []);

        if (!empty($source['options']) && is_array($source['options'])) {
            foreach ($source['options'] as $elementKey => $list) {
                $options[$elementKey] = self::map_detailed_options($list);
            }
        }
        return $options;
    }

    private static function map_code_label_options($list) {
        $mapped = [];
        if (!is_array($list)) return $mapped;
        foreach ($list as $idx => $item) {
            if (!is_array($item)) continue;
            $code = sanitize_key($item['code'] ?? '');
            $label = sanitize_text_field($item['label'] ?? '');
            if ($code === '' && $label === '') continue;
            $mapped[] = [
                'option_code' => $code !== '' ? $code : 'item_'.$idx,
                'label' => ['de' => $label, 'en' => $label],
                'info' => ['de' => '', 'en' => ''],
                'image_id' => 0,
                'is_default' => $idx === 0,
                'price' => null,
                'unit' => null,
            ];
        }
        return $mapped;
    }

    private static function map_model_options($models) {
        $mapped = [];
        if (!is_array($models)) return $mapped;
        foreach ($models as $idx => $model) {
            if (!is_array($model)) continue;
            $labelParts = array_filter([
                sanitize_text_field($model['family'] ?? ''),
                sanitize_text_field($model['name'] ?? ''),
            ]);
            $label = implode(' – ', $labelParts);
            if ($label === '') $label = __('Modell', 'schmitke-doors') . ' ' . ($idx + 1);
            $slugBase = sanitize_title($label ?: 'model-'.$idx);
            $mapped[] = [
                'option_code' => $slugBase,
                'label' => ['de' => $label, 'en' => $label],
                'info' => ['de' => '', 'en' => ''],
                'image_id' => intval($model['imageId'] ?? 0),
                'is_default' => $idx === 0,
                'price' => null,
                'unit' => null,
            ];
        }
        return $mapped;
    }

    private static function map_string_options($options) {
        $mapped = [];
        if (!is_array($options)) return $mapped;
        foreach ($options as $idx => $label) {
            $text = sanitize_text_field($label);
            if ($text === '') continue;
            $mapped[] = [
                'option_code' => sanitize_title($text ?: 'option-'.$idx),
                'label' => ['de' => $text, 'en' => $text],
                'info' => ['de' => '', 'en' => ''],
                'image_id' => 0,
                'is_default' => $idx === 0,
                'price' => null,
                'unit' => null,
            ];
        }
        return $mapped;
    }

    private static function map_construction_labels($labels) {
        $mapped = [];
        if (!is_array($labels)) return $mapped;
        $i = 0;
        foreach ($labels as $code => $label) {
            $mapped[] = [
                'option_code' => sanitize_key($code),
                'label' => [
                    'de' => sanitize_text_field($label ?? ''),
                    'en' => sanitize_text_field($label ?? ''),
                ],
                'info' => ['de' => '', 'en' => ''],
                'image_id' => 0,
                'is_default' => $i === 0,
                'price' => null,
                'unit' => null,
            ];
            $i++;
        }
        return $mapped;
    }

    private static function collect_la_options($models) {
        $set = [];
        if (!is_array($models)) return [];
        foreach ($models as $model) {
            if (!is_array($model) || empty($model['laOptions'])) continue;
            foreach ($model['laOptions'] as $la) {
                $text = sanitize_text_field($la);
                if ($text === '') continue;
                $set[$text] = true;
            }
        }
        return array_keys($set);
    }

    private static function map_detailed_options($list) {
        $mapped = [];
        if (!is_array($list)) return $mapped;
        foreach ($list as $idx => $item) {
            if (!is_array($item)) continue;
            $label = sanitize_text_field($item['title'] ?? $item['key'] ?? '');
            $mapped[] = [
                'option_code' => sanitize_key($item['key'] ?? 'option_'.$idx),
                'label' => ['de' => $label, 'en' => $label],
                'info' => ['de' => sanitize_text_field($item['description'] ?? ''), 'en' => sanitize_text_field($item['description'] ?? '')],
                'image_id' => intval($item['imageId'] ?? 0),
                'is_default' => $idx === 0,
                'price' => null,
                'unit' => null,
            ];
        }
        return $mapped;
    }
}

