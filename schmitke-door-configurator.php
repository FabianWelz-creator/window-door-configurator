<?php
/**
 * Plugin Name: Schmitke Türen Konfigurator (MVP) – v2.1
 * Description: Türen-Configurator als Shortcode mit übersichtlicher Admin-Verwaltung, WP-Mediathek für Bilder und Design-Optionen.
 * Version: 0.2.1
 * Author: fymito / Schmitke
 * Text Domain: schmitke-doors
 */

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__).'includes/config-normalizer.php';
require_once plugin_dir_path(__FILE__).'includes/config-settings-v2.php';

class Schmitke_Doors_Configurator_V21 {
    const OPT_KEY = 'schmitke_doors_configurator_data_v21';

    private function get_data() {
        $stored = get_option(self::OPT_KEY);
        if (!$stored || !is_array($stored)) return self::default_data();

        $defaults = self::default_data();

        $design = is_array($stored['design'] ?? null)
            ? array_merge($defaults['design'], $stored['design'])
            : $defaults['design'];

        $sizes = is_array($stored['sizes'] ?? null) ? [
            'dinWidthsMm' => array_values(array_filter(array_map('intval', $stored['sizes']['dinWidthsMm'] ?? $defaults['sizes']['dinWidthsMm']))),
            'dinHeightsMm' => array_values(array_filter(array_map('intval', $stored['sizes']['dinHeightsMm'] ?? $defaults['sizes']['dinHeightsMm'])))
        ] : $defaults['sizes'];

        $frames = (isset($stored['frames']) && is_array($stored['frames']) && !empty($stored['frames'])) ? $stored['frames'] : $defaults['frames'];
        $extras = (isset($stored['extras']) && is_array($stored['extras']) && !empty($stored['extras'])) ? $stored['extras'] : $defaults['extras'];

        $rules = ['constructionLabels' => []];
        $rulesSource = $stored['rules']['constructionLabels'] ?? $defaults['rules']['constructionLabels'];
        if (is_array($rulesSource)) {
            foreach ($rulesSource as $key => $label) {
                $rules['constructionLabels'][$key] = $label;
            }
        }
        if (empty($rules['constructionLabels'])) $rules = $defaults['rules'];

        $data = [
            'email_to' => isset($stored['email_to']) ? $stored['email_to'] : $defaults['email_to'],
            'design' => $design,
            'models' => (isset($stored['models']) && is_array($stored['models']) && !empty($stored['models'])) ? $stored['models'] : $defaults['models'],
            'sizes' => $sizes,
            'frames' => $frames,
            'extras' => $extras,
            'rules' => $rules,
            'elements_meta' => is_array($stored['elements_meta'] ?? null) ? $stored['elements_meta'] : [],
            'element_options' => is_array($stored['element_options'] ?? null) ? $stored['element_options'] : [],
        ];

        return Schmitke_Config_Normalizer::get_configurator_config($data, 'doors', self::default_data());
    }

    public function __construct() {
        add_action('plugins_loaded', function(){
            load_plugin_textdomain('schmitke-doors', false, dirname(plugin_basename(__FILE__)).'/languages');
        });
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('schmitke_doors_configurator', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }

    public static function default_data() {
        return [
            "email_to" => "info@schmitke-bauelemente.de",
            "design" => [
                "primaryColor" => "#111111",
                "accentColor"  => "#f2f2f2",
                "textColor"    => "#111111",
                "borderColor"  => "#e7e7e7",
                "fontFamily"   => "system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif",
                "buttonRadius" => 10,
                "cardRadius"   => 14
            ],
            "models" => [
                ["family"=>"Cuna","name"=>"Cuna 01","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Cuna","name"=>"Cuna 03","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Cuna","name"=>"Cuna 04","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Rupia","name"=>"Rupia 03","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Rupia","name"=>"Rupia 04","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Rupia","name"=>"Rupia 23","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Filan","name"=>"Filan 20","finish"=>"Arctic White / Weißlack","constructionDefault"=>"stumpf","laOptions"=>["LA Filan 20"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Landoor","name"=>"Landoor 1","finish"=>"Weißlack","constructionDefault"=>"gefaelzt","laOptions"=>["LA Landoor 1"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Landoor","name"=>"Landoor 2","finish"=>"Weißlack","constructionDefault"=>"gefaelzt","laOptions"=>["LA Landoor 2","Sprossenrahmen","ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Landoor","name"=>"Landoor 3","finish"=>"Weißlack","constructionDefault"=>"gefaelzt","laOptions"=>["2x LA Landoor 3"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Landoor","name"=>"Landoor 4","finish"=>"Weißlack","constructionDefault"=>"gefaelzt","laOptions"=>["3x LA Landoor 4","LA3 Landoor 4"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""],
                ["family"=>"Vicinia","name"=>"Vicinia 35","finish"=>"Serie nach Auswahl","constructionDefault"=>"gefaelzt","laOptions"=>["ohne LA"],"edgeDefault"=>"A2","imageId"=>0,"image"=>""]
            ],
            "sizes" => [
                "dinWidthsMm" => [610,735,860,985,1110,1235],
                "dinHeightsMm" => [1985,2110,2235]
            ],
            "frames" => [
                ["code"=>"RR","label"=>"RR – rund/rund"],
                ["code"=>"R1.5","label"=>"R1,5 – rund + 1,5mm Radius"],
                ["code"=>"R2","label"=>"R2 – fein gerundet"],
                ["code"=>"A2","label"=>"A2 – fein gerundet"],
                ["code"=>"PZ-4R","label"=>"PZ-4 R – profiliert + rund"],
                ["code"=>"PZ-9","label"=>"PZ-9 – rund + eckig"],
                ["code"=>"PZ-14R","label"=>"PZ-14 R – stumpf/flächenbündig"]
            ],
            "extras" => [
                ["code"=>"boddichtung","label"=>"Bodendichtung"],
                ["code"=>"lueftung","label"=>"Lüftungsgitter"],
                ["code"=>"spion","label"=>"Türspion (Standard 1400mm)"]
            ],
            "rules" => [
                "constructionLabels" => [
                    "stumpf"=>"Stumpf einschlagend",
                    "gefaelzt"=>"Gefälzt (Normfalz)"
                ],
                "ui_rules" => []
            ],
            'upload' => [
                'max_mb' => 5,
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp']
            ],
            'elements_meta' => [],
            'element_options' => []
        ];
    }

    public function register_assets() {
        wp_register_style('schmitke-doors-configurator', plugins_url('public/configurator.css', __FILE__), [], '0.2.1');
        wp_register_script('schmitke-doors-configurator', plugins_url('public/configurator.js', __FILE__), [], '0.2.1', true);

        $data = $this->get_data();
        if (!empty($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as &$m) {
                if (empty($m['image']) && !empty($m['imageId'])) {
                    $url = wp_get_attachment_image_url(intval($m['imageId']), 'large');
                    if ($url) $m['image'] = $url;
                }
            }
            unset($m);
        }
        $data['locale'] = (substr(get_locale(), 0, 2) === 'de') ? 'de' : 'en';
        wp_localize_script('schmitke-doors-configurator', 'SCHMITKE_DOORS_DATA', $data);
    }

    public function maybe_enqueue_assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post) return;
        if (has_shortcode($post->post_content, 'schmitke_doors_configurator')) {
            wp_enqueue_style('schmitke-doors-configurator');
            wp_enqueue_script('schmitke-doors-configurator');
        }
    }

    public function admin_assets($hook) {
        if ($hook !== 'settings_page_schmitke-doors-configurator') return;
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('schmitke-doors-admin', plugins_url('admin/admin.js', __FILE__), ['jquery','wp-color-picker'], '0.2.1', true);
        wp_enqueue_style('schmitke-doors-admin', plugins_url('admin/admin.css', __FILE__), [], '0.2.1');
    }

    public function admin_menu() {
        add_options_page(
            'Schmitke Türen Konfigurator',
            'Türen Konfigurator',
            'manage_options',
            'schmitke-doors-configurator',
            [$this, 'admin_page']
        );
    }

    public function register_settings() {
        register_setting('schmitke_doors_configurator_group', self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_data']
        ]);
        if (!get_option(self::OPT_KEY)) {
            add_option(self::OPT_KEY, self::default_data());
        }
    }

    public function sanitize_data($input) {
        if (!is_array($input)) return self::default_data();
        $defaults = self::default_data();
        $clean = $input;

        $clean['email_to'] = isset($input['email_to']) ? sanitize_email($input['email_to']) : $defaults['email_to'];

        $d = $input['design'] ?? [];
        $clean['design'] = [
            'primaryColor' => sanitize_hex_color($d['primaryColor'] ?? $defaults['design']['primaryColor']) ?: $defaults['design']['primaryColor'],
            'accentColor'  => sanitize_hex_color($d['accentColor'] ?? $defaults['design']['accentColor']) ?: $defaults['design']['accentColor'],
            'textColor'    => sanitize_hex_color($d['textColor'] ?? $defaults['design']['textColor']) ?: $defaults['design']['textColor'],
            'borderColor'  => sanitize_hex_color($d['borderColor'] ?? $defaults['design']['borderColor']) ?: $defaults['design']['borderColor'],
            'fontFamily'   => sanitize_text_field($d['fontFamily'] ?? $defaults['design']['fontFamily']),
            'buttonRadius' => max(0, intval($d['buttonRadius'] ?? $defaults['design']['buttonRadius'])),
            'cardRadius'   => max(0, intval($d['cardRadius'] ?? $defaults['design']['cardRadius']))
        ];

        $clean['models'] = [];
        if (isset($input['models']) && is_array($input['models'])) {
            foreach ($input['models'] as $m) {
                if (!is_array($m)) continue;
                $la = [];
                if (!empty($m['laOptionsRaw'])) {
                    $la = array_values(array_filter(array_map('sanitize_text_field', explode(',', $m['laOptionsRaw']))));
                } elseif (!empty($m['laOptions']) && is_array($m['laOptions'])) {
                    $la = array_values(array_filter(array_map('sanitize_text_field', $m['laOptions'])));
                }
                if (empty($la)) $la = ['ohne LA'];

                $imageId = intval($m['imageId'] ?? 0);
                $imageUrl = esc_url_raw($m['image'] ?? '');
                if ($imageId && !$imageUrl) {
                    $u = wp_get_attachment_image_url($imageId, 'large');
                    if ($u) $imageUrl = $u;
                }

                $clean['models'][] = [
                    'family' => sanitize_text_field($m['family'] ?? ''),
                    'name' => sanitize_text_field($m['name'] ?? ''),
                    'finish' => sanitize_text_field($m['finish'] ?? ''),
                    'constructionDefault' => in_array(($m['constructionDefault'] ?? ''), ['stumpf','gefaelzt'], true) ? $m['constructionDefault'] : 'stumpf',
                    'laOptions' => $la,
                    'edgeDefault' => sanitize_text_field($m['edgeDefault'] ?? 'A2'),
                    'imageId' => $imageId,
                    'image' => $imageUrl
                ];
            }
        }
        if (empty($clean['models'])) $clean['models'] = $defaults['models'];

        $clean['sizes'] = [
            'dinWidthsMm' => array_values(array_map('intval', self::parse_int_list($input['sizes']['dinWidthsMmRaw'] ?? implode(',', $defaults['sizes']['dinWidthsMm'])))),
            'dinHeightsMm' => array_values(array_map('intval', self::parse_int_list($input['sizes']['dinHeightsMmRaw'] ?? implode(',', $defaults['sizes']['dinHeightsMm'])))),
        ];

        $clean['frames'] = [];
        foreach (($input['frames'] ?? $defaults['frames']) as $f) {
            if (!is_array($f)) continue;
            $code = sanitize_text_field($f['code'] ?? '');
            $label = sanitize_text_field($f['label'] ?? '');
            if ($code === '' && $label === '') continue;
            $clean['frames'][] = [
                'code' => $code,
                'label' => $label,
            ];
        }
        if (empty($clean['frames'])) $clean['frames'] = $defaults['frames'];

        $clean['extras'] = [];
        foreach (($input['extras'] ?? $defaults['extras']) as $ex) {
            if (!is_array($ex)) continue;
            $code = sanitize_text_field($ex['code'] ?? '');
            $label = sanitize_text_field($ex['label'] ?? '');
            if ($code === '' && $label === '') continue;
            $clean['extras'][] = [
                'code' => $code,
                'label' => $label,
            ];
        }
        if (empty($clean['extras'])) $clean['extras'] = $defaults['extras'];

        $clean['rules'] = ['constructionLabels' => []];
        $ruleSrc = $input['rules']['constructionLabels'] ?? $defaults['rules']['constructionLabels'];
        if (is_array($ruleSrc)) {
            foreach ($ruleSrc as $key => $label) {
                $key = sanitize_text_field((string)$key);
                $cleanLabel = sanitize_text_field($label ?? '');
                if ($key === '' && $cleanLabel === '') continue;
                $clean['rules']['constructionLabels'][$key] = $cleanLabel;
            }
        }
        if (empty($clean['rules']['constructionLabels'])) $clean['rules'] = $defaults['rules'];

        $clean['elements_meta'] = Schmitke_Config_Normalizer::sanitize_elements_meta(
            $input['elements_meta'] ?? [],
            Schmitke_Config_Normalizer::default_elements_meta('doors')
        );

        $clean['element_options'] = Schmitke_Config_Normalizer::sanitize_element_options(
            $input['element_options'] ?? [],
            'doors',
            $clean
        );
        return $clean;
    }

    public static function parse_int_list($raw) {
        $parts = preg_split('/[\s,;]+/', (string)$raw);
        return array_values(array_filter(array_map('intval', $parts)));
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $data = $this->get_data();
        $opt = self::OPT_KEY;
        ?>
        <div class="wrap schmitke-admin">
            <h1>Schmitke Türen Konfigurator – Einstellungen</h1>

            <form method="post" action="options.php">
                <?php settings_fields('schmitke_doors_configurator_group'); ?>

                <div class="schmitke-section">
                    <h2>Empfänger E‑Mail</h2>
                    <input type="email" name="<?php echo esc_attr($opt); ?>[email_to]"
                           value="<?php echo esc_attr($data['email_to'] ?? ''); ?>" class="regular-text">
                </div>

                <div class="schmitke-section">
                    <h2>Design</h2>
                    <div class="schmitke-design-grid">
                        <label>Primary Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][primaryColor]"
                                   value="<?php echo esc_attr($data['design']['primaryColor'] ?? '#111111'); ?>">
                        </label>
                        <label>Accent Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][accentColor]"
                                   value="<?php echo esc_attr($data['design']['accentColor'] ?? '#f2f2f2'); ?>">
                        </label>
                        <label>Text Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][textColor]"
                                   value="<?php echo esc_attr($data['design']['textColor'] ?? '#111111'); ?>">
                        </label>
                        <label>Border Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][borderColor]"
                                   value="<?php echo esc_attr($data['design']['borderColor'] ?? '#e7e7e7'); ?>">
                        </label>
                        <label>Font Family
                            <input type="text" name="<?php echo esc_attr($opt); ?>[design][fontFamily]"
                                   value="<?php echo esc_attr($data['design']['fontFamily'] ?? 'system-ui'); ?>" class="regular-text">
                        </label>
                        <label>Button Radius (px)
                            <input type="number" min="0" max="40"
                                   name="<?php echo esc_attr($opt); ?>[design][buttonRadius]"
                                   value="<?php echo esc_attr($data['design']['buttonRadius'] ?? 10); ?>">
                        </label>
                        <label>Card Radius (px)
                            <input type="number" min="0" max="40"
                                   name="<?php echo esc_attr($opt); ?>[design][cardRadius]"
                                   value="<?php echo esc_attr($data['design']['cardRadius'] ?? 14); ?>">
                        </label>
                    </div>
                </div>

                <div class="schmitke-section">
                    <h2>Türmodelle</h2>

                    <div id="schmitke-model-list">
                        <?php foreach (($data['models'] ?? []) as $i => $m): ?>
                            <?php
                                $imagePreview = '';
                                if (!empty($m['imageId'])) $imagePreview = wp_get_attachment_image_url(intval($m['imageId']), 'thumbnail');
                                if (!$imagePreview && !empty($m['image'])) $imagePreview = esc_url($m['image']);
                                $laRaw = is_array($m['laOptions']) ? implode(',', $m['laOptions']) : '';
                            ?>
                            <div class="schmitke-card schmitke-model-card">
                                <div class="schmitke-model-head">
                                    <div>
                                        <strong class="schmitke-model-title"><?php echo esc_html($m['family'].' – '.$m['name']); ?></strong>
                                        <div class="schmitke-model-sub"><?php echo esc_html($m['finish']); ?></div>
                                    </div>
                                    <div class="schmitke-model-actions">
                                        <button type="button" class="button schmitke-toggle">Öffnen</button>
                                        <button type="button" class="button button-link-delete schmitke-remove">Löschen</button>
                                    </div>
                                </div>

                                <div class="schmitke-model-body">
                                    <div class="schmitke-model-grid">
                                        <label>Familie
                                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][family]"
                                                   value="<?php echo esc_attr($m['family']); ?>">
                                        </label>
                                        <label>Modellname
                                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][name]"
                                                   value="<?php echo esc_attr($m['name']); ?>">
                                        </label>
                                        <label>Finish
                                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][finish]"
                                                   value="<?php echo esc_attr($m['finish']); ?>">
                                        </label>
                                        <label>Bauart Default
                                            <select name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][constructionDefault]">
                                                <option value="stumpf" <?php selected($m['constructionDefault'],'stumpf'); ?>>stumpf</option>
                                                <option value="gefaelzt" <?php selected($m['constructionDefault'],'gefaelzt'); ?>>gefälzt</option>
                                            </select>
                                        </label>
                                        <label>Lichtausschnitt Optionen (Komma)
                                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][laOptionsRaw]"
                                                   value="<?php echo esc_attr($laRaw); ?>">
                                        </label>

                                        <div class="schmitke-image-field">
                                            <label>Bild (WP-Mediathek)</label>
                                            <div class="schmitke-image-row">
                                                <img class="schmitke-image-preview" src="<?php echo esc_url($imagePreview); ?>" alt="" />
                                                <div class="schmitke-image-buttons">
                                                    <button type="button" class="button schmitke-pick-image">Bild wählen</button>
                                                    <button type="button" class="button schmitke-clear-image">Entfernen</button>
                                                </div>
                                            </div>
                                            <input type="hidden" class="schmitke-image-id"
                                                   name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][imageId]"
                                                   value="<?php echo esc_attr($m['imageId'] ?? 0); ?>">
                                            <input type="hidden" class="schmitke-image-url"
                                                   name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][image]"
                                                   value="<?php echo esc_attr($m['image'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p><button type="button" class="button button-secondary" id="schmitke-add-model">+ Modell hinzufügen</button></p>
                </div>

                <div class="schmitke-section">
                    <h2>DIN‑Maße</h2>
                    <div class="schmitke-din-grid">
                        <label>Breiten (mm), Komma getrennt
                            <input type="text" name="<?php echo esc_attr($opt); ?>[sizes][dinWidthsMmRaw]"
                                   value="<?php echo esc_attr(implode(',', $data['sizes']['dinWidthsMm'] ?? [])); ?>" class="regular-text">
                        </label>
                        <label>Höhen (mm), Komma getrennt
                            <input type="text" name="<?php echo esc_attr($opt); ?>[sizes][dinHeightsMmRaw]"
                                   value="<?php echo esc_attr(implode(',', $data['sizes']['dinHeightsMm'] ?? [])); ?>" class="regular-text">
                        </label>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <script type="text/template" id="schmitke-model-template">
            <div class="schmitke-card schmitke-model-card">
                <div class="schmitke-model-head">
                    <div>
                        <strong class="schmitke-model-title">Neues Modell</strong>
                        <div class="schmitke-model-sub"></div>
                    </div>
                    <div class="schmitke-model-actions">
                        <button type="button" class="button schmitke-toggle">Öffnen</button>
                        <button type="button" class="button button-link-delete schmitke-remove">Löschen</button>
                    </div>
                </div>
                <div class="schmitke-model-body">
                    <div class="schmitke-model-grid">
                        <label>Familie
                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][__i__][family]" value="">
                        </label>
                        <label>Modellname
                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][__i__][name]" value="">
                        </label>
                        <label>Finish
                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][__i__][finish]" value="">
                        </label>
                        <label>Bauart Default
                            <select name="<?php echo esc_attr($opt); ?>[models][__i__][constructionDefault]">
                                <option value="stumpf">stumpf</option>
                                <option value="gefaelzt">gefälzt</option>
                            </select>
                        </label>
                        <label>Lichtausschnitt Optionen (Komma)
                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][__i__][laOptionsRaw]" value="ohne LA">
                        </label>

        <div class="schmitke-image-field">
            <label>Bild (WP-Mediathek)</label>
            <div class="schmitke-image-row">
                <img class="schmitke-image-preview" src="" alt="" />
                <div class="schmitke-image-buttons">
                                    <button type="button" class="button schmitke-pick-image">Bild wählen</button>
                                    <button type="button" class="button schmitke-clear-image">Entfernen</button>
                                </div>
                            </div>
                            <input type="hidden" class="schmitke-image-id"
                                   name="<?php echo esc_attr($opt); ?>[models][__i__][imageId]" value="0">
                            <input type="hidden" class="schmitke-image-url"
                                   name="<?php echo esc_attr($opt); ?>[models][__i__][image]" value="">
                        </div>
                    </div>
                </div>
            </div>
        </script>
                <?php
    }

    public function shortcode($atts) {
        // enqueue here too (Elementor sometimes renders shortcodes before wp_enqueue_scripts finishes)
        wp_enqueue_style('schmitke-doors-configurator');
        wp_enqueue_script('schmitke-doors-configurator');
        return '<div id="schmitke-door-configurator"></div>';
    }
}

new Schmitke_Doors_Configurator_V21();

class Schmitke_Windows_Configurator {
    const OPT_KEY = 'schmitke_windows_configurator_data';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('schmitke_windows_configurator', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }

    public static function default_data() {
        return [
            'email_to' => 'info@schmitke-bauelemente.de',
            'design' => [
                'primaryColor' => '#111111',
                'accentColor'  => '#f2f2f2',
                'textColor'    => '#111111',
                'borderColor'  => '#e7e7e7',
                'fontFamily'   => 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif',
                'buttonRadius' => 10,
                'cardRadius'   => 14
            ],
            'models' => [
                [
                    'family' => 'Classic',
                    'name' => 'Classic 70',
                    'materialDefault' => 'alu',
                    'frameDefault' => 'classic',
                    'glassDefault' => '2-fach',
                    'imageId' => 0,
                    'image' => ''
                ],
                [
                    'family' => 'Modern',
                    'name' => 'Modern Panorama',
                    'materialDefault' => 'kunststoff',
                    'frameDefault' => 'schmal',
                    'glassDefault' => '3-fach',
                    'imageId' => 0,
                    'image' => ''
                ]
            ],
            'materials' => [
                ['code' => 'alu', 'label' => 'Aluminium'],
                ['code' => 'kunststoff', 'label' => 'Kunststoff'],
                ['code' => 'holz', 'label' => 'Holz']
            ],
            'frames' => [
                ['code' => 'classic', 'label' => 'Klassischer Rahmen'],
                ['code' => 'schmal', 'label' => 'Schmaler Rahmen'],
                ['code' => 'passiv', 'label' => 'Passivhaus Rahmen']
            ],
            'glass' => [
                ['code' => '2-fach', 'label' => '2-fach Verglasung'],
                ['code' => '3-fach', 'label' => '3-fach Verglasung'],
                ['code' => 'sicherheitsglas', 'label' => 'Sicherheitsglas']
            ],
            'extras' => [
                ['code' => 'falzluft', 'label' => 'Falzlüfter'],
                ['code' => 'rollo', 'label' => 'Integriertes Rollo'],
                ['code' => 'einbruch', 'label' => 'Einbruchschutz-Beschlag']
            ],
            'options' => [
                'type' => [
                    [
                        'key' => 'window',
                        'title' => 'Fenster',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'balcony',
                        'title' => 'Balkontür',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'sliding',
                        'title' => 'Schiebetür',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ]
                ],
                'manufacturer' => [
                    [
                        'key' => 'veka',
                        'title' => 'VEKA',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'schueco',
                        'title' => 'Schüco',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ]
                ],
                'profile' => [
                    [
                        'key' => 'veka-softline-82',
                        'title' => 'VEKA Softline 82',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'schueco-living',
                        'title' => 'Schüco LivIng',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ]
                ],
                'colorExterior' => [],
                'colorInterior' => [],
                'form' => [
                    [
                        'key' => 'rechteck',
                        'title' => 'Rechteck',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'rundbogen',
                        'title' => 'Rundbogen',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'segmentbogen',
                        'title' => 'Segmentbogen',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'rund',
                        'title' => 'Rund',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'dreieck',
                        'title' => 'Dreieck',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ]
                ],
                'sashes' => [
                    [
                        'key' => '1-fluegel',
                        'title' => '1 Flügel',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => '2-fluegel',
                        'title' => '2 Flügel',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => '3-fluegel',
                        'title' => '3 Flügel',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => '4-fluegel',
                        'title' => '4 Flügel',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ]
                ],
                'opening' => [
                    [
                        'key' => 'dreh-kipp',
                        'title' => 'Dreh-Kipp',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'festverglast',
                        'title' => 'Festverglast',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'psk',
                        'title' => 'Parallel-Schiebe-Kipp',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'hebe-schiebe',
                        'title' => 'Hebe-Schiebe',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ],
                    [
                        'key' => 'klapp',
                        'title' => 'Klappflügel',
                        'subtitle' => '',
                        'imageId' => 0,
                        'image' => '',
                        'description' => ''
                    ]
                ]
            ],
            'rules' => [
                'constructionLabels' => [],
                'ui_rules' => []
            ],
            'upload' => [
                'max_mb' => 5,
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp']
            ],
            'elements_meta' => [],
            'element_options' => []
        ];
    }

    private function get_data() {
        $stored = get_option(self::OPT_KEY);
        if (!$stored || !is_array($stored)) return self::default_data();

        $defaults = self::default_data();

        $design = is_array($stored['design'] ?? null)
            ? array_merge($defaults['design'], $stored['design'])
            : $defaults['design'];

        $models = (isset($stored['models']) && is_array($stored['models']) && !empty($stored['models']))
            ? $stored['models']
            : $defaults['models'];

        $materials = (isset($stored['materials']) && is_array($stored['materials']) && !empty($stored['materials']))
            ? $stored['materials']
            : $defaults['materials'];

        $frames = (isset($stored['frames']) && is_array($stored['frames']) && !empty($stored['frames']))
            ? $stored['frames']
            : $defaults['frames'];

        $glass = (isset($stored['glass']) && is_array($stored['glass']) && !empty($stored['glass']))
            ? $stored['glass']
            : $defaults['glass'];

        $extras = (isset($stored['extras']) && is_array($stored['extras']) && !empty($stored['extras']))
            ? $stored['extras']
            : $defaults['extras'];

        $options = $defaults['options'];
        if (isset($stored['options']) && is_array($stored['options'])) {
            foreach ($defaults['options'] as $key => $defaultList) {
                if (isset($stored['options'][$key]) && is_array($stored['options'][$key]) && !empty($stored['options'][$key])) {
                    $options[$key] = $stored['options'][$key];
                }
            }
        }

        $data = [
            'email_to' => isset($stored['email_to']) ? $stored['email_to'] : $defaults['email_to'],
            'design' => $design,
            'models' => $models,
            'materials' => $materials,
            'frames' => $frames,
            'glass' => $glass,
            'extras' => $extras,
            'options' => $options,
            'elements_meta' => is_array($stored['elements_meta'] ?? null) ? $stored['elements_meta'] : [],
            'element_options' => is_array($stored['element_options'] ?? null) ? $stored['element_options'] : [],
        ];

        return Schmitke_Config_Normalizer::get_configurator_config($data, 'windows', self::default_data());
    }

    public function register_assets() {
        wp_register_style('schmitke-windows-configurator', plugins_url('public/configurator.css', __FILE__), [], '0.2.1');
        wp_register_script('schmitke-windows-configurator', plugins_url('public/configurator-windows.js', __FILE__), [], '0.2.1', true);

        $data = $this->get_data();
        $data['v2'] = Schmitke_Configurator_Settings_V2::get_settings();
        if (!empty($data['v2']['design'])) {
            $data['design'] = array_merge($data['design'] ?? [], $data['v2']['design']);
        }
        if (!empty($data['v2']['options_by_element']) && is_array($data['v2']['options_by_element'])) {
            foreach ($data['v2']['options_by_element'] as &$optionGroup) {
                if (!is_array($optionGroup)) continue;
                foreach ($optionGroup as &$opt) {
                    if (!is_array($opt)) continue;
                    if (empty($opt['image']) && !empty($opt['image_id'])) {
                        $url = wp_get_attachment_image_url(intval($opt['image_id']), 'large');
                        if ($url) {
                            $opt['image'] = $url;
                        }
                    }
                }
                unset($opt);
            }
            unset($optionGroup);
        }
        $data['v2_enabled'] = !empty($data['v2']['elements']);
        if (!empty($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as &$m) {
                if (empty($m['image']) && !empty($m['imageId'])) {
                    $url = wp_get_attachment_image_url(intval($m['imageId']), 'large');
                    if ($url) $m['image'] = $url;
                }
            }
            unset($m);
        }

        if (!empty($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as &$optionGroup) {
                if (!is_array($optionGroup)) continue;
                foreach ($optionGroup as &$opt) {
                    if (!is_array($opt)) continue;
                    if (empty($opt['image']) && !empty($opt['imageId'])) {
                        $url = wp_get_attachment_image_url(intval($opt['imageId']), 'large');
                        if ($url) $opt['image'] = $url;
                    }
                }
                unset($opt);
            }
            unset($optionGroup);
        }

        $data['locale'] = (substr(get_locale(), 0, 2) === 'de') ? 'de' : 'en';

        wp_localize_script('schmitke-windows-configurator', 'SCHMITKE_WINDOWS_DATA', $data);
    }

    public function maybe_enqueue_assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post) return;
        if (has_shortcode($post->post_content, 'schmitke_windows_configurator')) {
            wp_enqueue_style('schmitke-windows-configurator');
            wp_enqueue_script('schmitke-windows-configurator');
        }
    }

    public function admin_assets($hook) {
        if (!in_array($hook, ['settings_page_schmitke-windows-configurator', 'settings_page_schmitke-doors-configurator'], true)) return;
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('schmitke-doors-admin', plugins_url('admin/admin.js', __FILE__), ['jquery','wp-color-picker'], '0.2.1', true);
        wp_enqueue_style('schmitke-doors-admin', plugins_url('admin/admin.css', __FILE__), [], '0.2.1');
        wp_enqueue_script(
            'schmitke-configurator-v2-admin',
            plugins_url('admin/configurator-v2.js', __FILE__),
            ['jquery', 'jquery-ui-sortable', 'wp-util'],
            '0.2.1',
            true
        );
        wp_localize_script('schmitke-configurator-v2-admin', 'SCHMITKE_CONFIG_V2_ADMIN', [
            'settings' => Schmitke_Configurator_Settings_V2::get_settings(),
            'optionKey' => Schmitke_Configurator_Settings_V2::OPTION_KEY,
        ]);
    }

    public function admin_menu() {
        add_options_page(
            'Schmitke Fenster Konfigurator',
            'Fenster Konfigurator',
            'manage_options',
            'schmitke-windows-configurator',
            [$this, 'admin_page']
        );
    }

    public function register_settings() {
        register_setting('schmitke_windows_configurator_group', self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_data']
        ]);
        if (!get_option(self::OPT_KEY)) {
            add_option(self::OPT_KEY, self::default_data());
        }
    }

    public function sanitize_data($input) {
        if (!is_array($input)) return self::default_data();
        $defaults = self::default_data();
        $clean = $input;

        $clean['email_to'] = isset($input['email_to']) ? sanitize_email($input['email_to']) : $defaults['email_to'];

        $d = $input['design'] ?? [];
        $clean['design'] = [
            'primaryColor' => sanitize_hex_color($d['primaryColor'] ?? $defaults['design']['primaryColor']) ?: $defaults['design']['primaryColor'],
            'accentColor'  => sanitize_hex_color($d['accentColor'] ?? $defaults['design']['accentColor']) ?: $defaults['design']['accentColor'],
            'textColor'    => sanitize_hex_color($d['textColor'] ?? $defaults['design']['textColor']) ?: $defaults['design']['textColor'],
            'borderColor'  => sanitize_hex_color($d['borderColor'] ?? $defaults['design']['borderColor']) ?: $defaults['design']['borderColor'],
            'fontFamily'   => sanitize_text_field($d['fontFamily'] ?? $defaults['design']['fontFamily']),
            'buttonRadius' => max(0, intval($d['buttonRadius'] ?? $defaults['design']['buttonRadius'])),
            'cardRadius'   => max(0, intval($d['cardRadius'] ?? $defaults['design']['cardRadius']))
        ];

        $clean['materials'] = $this->sanitize_code_label_list($input['materials'] ?? $defaults['materials'], $defaults['materials']);
        $clean['frames'] = $this->sanitize_code_label_list($input['frames'] ?? $defaults['frames'], $defaults['frames']);
        $clean['glass'] = $this->sanitize_code_label_list($input['glass'] ?? $defaults['glass'], $defaults['glass']);
        $clean['extras'] = $this->sanitize_code_label_list($input['extras'] ?? $defaults['extras'], $defaults['extras']);

        $clean['options'] = $this->sanitize_options($input['options'] ?? $defaults['options'], $defaults['options']);

        $materialCodes = array_column($clean['materials'], 'code');
        $frameCodes = array_column($clean['frames'], 'code');
        $glassCodes = array_column($clean['glass'], 'code');

        $clean['models'] = [];
        if (isset($input['models']) && is_array($input['models'])) {
            foreach ($input['models'] as $m) {
                if (!is_array($m)) continue;

                $imageId = intval($m['imageId'] ?? 0);
                $imageUrl = esc_url_raw($m['image'] ?? '');
                if ($imageId && !$imageUrl) {
                    $u = wp_get_attachment_image_url($imageId, 'large');
                    if ($u) $imageUrl = $u;
                }

                $clean['models'][] = [
                    'family' => sanitize_text_field($m['family'] ?? ''),
                    'name' => sanitize_text_field($m['name'] ?? ''),
                    'materialDefault' => in_array(($m['materialDefault'] ?? ''), $materialCodes, true) ? $m['materialDefault'] : ($materialCodes[0] ?? ''),
                    'frameDefault' => in_array(($m['frameDefault'] ?? ''), $frameCodes, true) ? $m['frameDefault'] : ($frameCodes[0] ?? ''),
                    'glassDefault' => in_array(($m['glassDefault'] ?? ''), $glassCodes, true) ? $m['glassDefault'] : ($glassCodes[0] ?? ''),
                    'imageId' => $imageId,
                    'image' => $imageUrl
                ];
            }
        }
        if (empty($clean['models'])) $clean['models'] = $defaults['models'];

        $clean['elements_meta'] = Schmitke_Config_Normalizer::sanitize_elements_meta(
            $input['elements_meta'] ?? [],
            Schmitke_Config_Normalizer::default_elements_meta('windows')
        );

        $clean['element_options'] = Schmitke_Config_Normalizer::sanitize_element_options(
            $input['element_options'] ?? [],
            'windows',
            $clean
        );

        return $clean;
    }

    private function sanitize_code_label_list($input, $fallback) {
        $clean = [];
        if (is_array($input)) {
            foreach ($input as $item) {
                if (!is_array($item)) continue;
                $code = sanitize_text_field($item['code'] ?? '');
                $label = sanitize_text_field($item['label'] ?? '');
                if ($code === '' && $label === '') continue;
                $clean[] = [
                    'code' => $code,
                    'label' => $label,
                ];
            }
        }
        if (empty($clean)) return $fallback;
        return $clean;
    }

    private function sanitize_options($input, $fallback) {
        $clean = [];
        if (!is_array($fallback)) return [];

        foreach ($fallback as $key => $defaultList) {
            $clean[$key] = $this->sanitize_option_items($input[$key] ?? $defaultList, $defaultList);
        }

        return $clean;
    }

    private function sanitize_option_items($input, $fallback) {
        $clean = [];
        if (is_array($input)) {
            foreach ($input as $item) {
                if (!is_array($item)) continue;

                $key = sanitize_text_field($item['key'] ?? '');
                $title = sanitize_text_field($item['title'] ?? '');
                $subtitle = sanitize_text_field($item['subtitle'] ?? '');
                $description = sanitize_text_field($item['description'] ?? '');
                $imageId = intval($item['imageId'] ?? 0);
                $image = esc_url_raw($item['image'] ?? '');

                if ($key === '' && $title === '' && !$imageId && $image === '') continue;

                if ($imageId && !$image) {
                    $url = wp_get_attachment_image_url($imageId, 'large');
                    if ($url) $image = $url;
                }

                $clean[] = [
                    'key' => $key,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'imageId' => $imageId,
                    'image' => $image,
                    'description' => $description,
                ];
            }
        }

        if (empty($clean)) return $fallback;
        return $clean;
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'legacy';
        if ($tab === 'v2') {
            $this->render_windows_v2_admin();
            return;
        }
        $data = $this->get_data();
        $opt = self::OPT_KEY;
        ?>
        <div class="wrap schmitke-admin">
            <h1>Schmitke Fenster Konfigurator – Einstellungen</h1>

            <?php $this->render_windows_nav_tabs($tab); ?>

            <form method="post" action="options.php">
                <?php settings_fields('schmitke_windows_configurator_group'); ?>

                <div class="schmitke-section">
                    <h2>Empfänger E‑Mail</h2>
                    <input type="email" name="<?php echo esc_attr($opt); ?>[email_to]"
                           value="<?php echo esc_attr($data['email_to'] ?? ''); ?>" class="regular-text">
                </div>

                <div class="schmitke-section">
                    <h2>Design</h2>
                    <div class="schmitke-design-grid">
                        <label>Primary Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][primaryColor]"
                                   value="<?php echo esc_attr($data['design']['primaryColor'] ?? '#111111'); ?>">
                        </label>
                        <label>Accent Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][accentColor]"
                                   value="<?php echo esc_attr($data['design']['accentColor'] ?? '#f2f2f2'); ?>">
                        </label>
                        <label>Text Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][textColor]"
                                   value="<?php echo esc_attr($data['design']['textColor'] ?? '#111111'); ?>">
                        </label>
                        <label>Border Color
                            <input type="text" class="schmitke-color"
                                   name="<?php echo esc_attr($opt); ?>[design][borderColor]"
                                   value="<?php echo esc_attr($data['design']['borderColor'] ?? '#e7e7e7'); ?>">
                        </label>
                        <label>Font Family
                            <input type="text" name="<?php echo esc_attr($opt); ?>[design][fontFamily]"
                                   value="<?php echo esc_attr($data['design']['fontFamily'] ?? 'system-ui'); ?>" class="regular-text">
                        </label>
                        <label>Button Radius (px)
                            <input type="number" min="0" max="40"
                                   name="<?php echo esc_attr($opt); ?>[design][buttonRadius]"
                                   value="<?php echo esc_attr($data['design']['buttonRadius'] ?? 10); ?>">
                        </label>
                        <label>Card Radius (px)
                            <input type="number" min="0" max="40"
                                   name="<?php echo esc_attr($opt); ?>[design][cardRadius]"
                                   value="<?php echo esc_attr($data['design']['cardRadius'] ?? 14); ?>">
                        </label>
                    </div>
                </div>

                <div class="schmitke-section">
                    <h2>Fenstermodelle</h2>

                    <div id="schmitke-model-list">
                        <?php foreach (($data['models'] ?? []) as $i => $m): ?>
                            <?php
                                $imagePreview = '';
                                if (!empty($m['imageId'])) $imagePreview = wp_get_attachment_image_url(intval($m['imageId']), 'thumbnail');
                                if (!$imagePreview && !empty($m['image'])) $imagePreview = esc_url($m['image']);
                            ?>
                            <div class="schmitke-card schmitke-model-card">
                                <div class="schmitke-model-head">
                                    <div>
                                        <strong class="schmitke-model-title"><?php echo esc_html(($m['family'] ?? '') . ' – ' . ($m['name'] ?? '')); ?></strong>
                                        <div class="schmitke-model-sub"><?php echo esc_html($m['materialDefault'] ?? ''); ?></div>
                                    </div>
                                    <div class="schmitke-model-actions">
                                        <button type="button" class="button schmitke-toggle">Öffnen</button>
                                        <button type="button" class="button button-link-delete schmitke-remove">Löschen</button>
                                    </div>
                                </div>

                                <div class="schmitke-model-body">
                                    <div class="schmitke-model-grid">
                                        <label>Familie
                                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][family]"
                                                   value="<?php echo esc_attr($m['family']); ?>">
                                        </label>
                                        <label>Modellname
                                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][name]"
                                                   value="<?php echo esc_attr($m['name']); ?>">
                                        </label>
                                        <label>Material Default
                                            <select name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][materialDefault]">
                                                <?php foreach (($data['materials'] ?? []) as $mat): ?>
                                                    <option value="<?php echo esc_attr($mat['code']); ?>" <?php selected($m['materialDefault'], $mat['code']); ?>><?php echo esc_html($mat['label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Rahmen Default
                                            <select name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][frameDefault]">
                                                <?php foreach (($data['frames'] ?? []) as $frame): ?>
                                                    <option value="<?php echo esc_attr($frame['code']); ?>" <?php selected($m['frameDefault'], $frame['code']); ?>><?php echo esc_html($frame['label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Glas Default
                                            <select name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][glassDefault]">
                                                <?php foreach (($data['glass'] ?? []) as $glass): ?>
                                                    <option value="<?php echo esc_attr($glass['code']); ?>" <?php selected($m['glassDefault'], $glass['code']); ?>><?php echo esc_html($glass['label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <div class="schmitke-image-field">
                                            <label>Bild (WP-Mediathek)</label>
                                            <div class="schmitke-image-row">
                                                <img class="schmitke-image-preview" src="<?php echo esc_url($imagePreview); ?>" alt="" />
                                                <div class="schmitke-image-buttons">
                                                    <button type="button" class="button schmitke-pick-image">Bild wählen</button>
                                                    <button type="button" class="button schmitke-clear-image">Entfernen</button>
                                                </div>
                                            </div>
                                            <input type="hidden" class="schmitke-image-id"
                                                   name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][imageId]"
                                                   value="<?php echo esc_attr($m['imageId'] ?? 0); ?>">
                                            <input type="hidden" class="schmitke-image-url"
                                                   name="<?php echo esc_attr($opt); ?>[models][<?php echo $i; ?>][image]"
                                                   value="<?php echo esc_attr($m['image'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p><button type="button" class="button button-secondary" id="schmitke-add-model">+ Modell hinzufügen</button></p>
                </div>

                <div class="schmitke-section">
                    <h2>Materialien</h2>
                    <table class="form-table">
                        <tbody>
                        <?php $materials = array_merge($data['materials'] ?? [], [['code' => '', 'label' => '']]); ?>
                        <?php foreach ($materials as $i => $mat): ?>
                            <tr>
                                <th scope="row">Material <?php echo $i + 1; ?></th>
                                <td>
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[materials][<?php echo $i; ?>][code]" value="<?php echo esc_attr($mat['code']); ?>" placeholder="Code">
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[materials][<?php echo $i; ?>][label]" value="<?php echo esc_attr($mat['label']); ?>" placeholder="Label" class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="schmitke-section">
                    <h2>Rahmen</h2>
                    <table class="form-table">
                        <tbody>
                        <?php $frames = array_merge($data['frames'] ?? [], [['code' => '', 'label' => '']]); ?>
                        <?php foreach ($frames as $i => $frame): ?>
                            <tr>
                                <th scope="row">Rahmen <?php echo $i + 1; ?></th>
                                <td>
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[frames][<?php echo $i; ?>][code]" value="<?php echo esc_attr($frame['code']); ?>" placeholder="Code">
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[frames][<?php echo $i; ?>][label]" value="<?php echo esc_attr($frame['label']); ?>" placeholder="Label" class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="schmitke-section">
                    <h2>Glas</h2>
                    <table class="form-table">
                        <tbody>
                        <?php $glass = array_merge($data['glass'] ?? [], [['code' => '', 'label' => '']]); ?>
                        <?php foreach ($glass as $i => $g): ?>
                            <tr>
                                <th scope="row">Glas <?php echo $i + 1; ?></th>
                                <td>
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[glass][<?php echo $i; ?>][code]" value="<?php echo esc_attr($g['code']); ?>" placeholder="Code">
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[glass][<?php echo $i; ?>][label]" value="<?php echo esc_attr($g['label']); ?>" placeholder="Label" class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="schmitke-section">
                    <h2>Extras</h2>
                    <table class="form-table">
                        <tbody>
                        <?php $extras = array_merge($data['extras'] ?? [], [['code' => '', 'label' => '']]); ?>
                        <?php foreach ($extras as $i => $ex): ?>
                            <tr>
                                <th scope="row">Extra <?php echo $i + 1; ?></th>
                                <td>
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[extras][<?php echo $i; ?>][code]" value="<?php echo esc_attr($ex['code']); ?>" placeholder="Code">
                                    <input type="text" name="<?php echo esc_attr($opt); ?>[extras][<?php echo $i; ?>][label]" value="<?php echo esc_attr($ex['label']); ?>" placeholder="Label" class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $optionGroups = [
                    'type' => 'Typ',
                    'manufacturer' => 'Hersteller',
                    'profile' => 'Profil',
                    'form' => 'Form',
                    'sashes' => 'Flügel',
                    'opening' => 'Öffnungsart',
                    'colorExterior' => 'Außenfarben',
                    'colorInterior' => 'Innenfarben',
                ];
                ?>

                <div class="schmitke-section">
                    <h2>Fenster-Optionen</h2>
                    <div class="schmitke-option-accordion">
                        <?php foreach ($optionGroups as $groupKey => $groupLabel): ?>
                            <?php $optionsList = $data['options'][$groupKey] ?? []; ?>
                            <div class="schmitke-option-panel open" data-group="<?php echo esc_attr($groupKey); ?>">
                                <button type="button" class="button schmitke-option-toggle" aria-expanded="true">
                                    <?php echo esc_html($groupLabel); ?>
                                    <span class="schmitke-option-toggle-icon">▼</span>
                                </button>
                                <div class="schmitke-option-panel-body">
                                    <div class="schmitke-option-list" data-group="<?php echo esc_attr($groupKey); ?>">
                                        <?php foreach ($optionsList as $i => $option): ?>
                                            <?php
                                                $imagePreview = '';
                                                if (!empty($option['image'])) $imagePreview = $option['image'];
                                                elseif (!empty($option['imageId'])) {
                                                    $u = wp_get_attachment_image_url(intval($option['imageId']), 'thumbnail');
                                                    if ($u) $imagePreview = $u;
                                                }
                                            ?>
                                            <div class="schmitke-card schmitke-option-card">
                                                <div class="schmitke-card-head">
                                                    <div>
                                                        <strong class="schmitke-card-title"><?php echo esc_html($option['title'] ?? 'Option'); ?></strong>
                                                        <?php
                                                        $subParts = array_filter([
                                                            $option['key'] ?? '',
                                                            $option['subtitle'] ?? ''
                                                        ], function($p) { return $p !== ''; });
                                                        ?>
                                                        <div class="schmitke-card-sub"><?php echo esc_html(implode(' – ', $subParts)); ?></div>
                                                    </div>
                                                    <div class="schmitke-model-actions">
                                                        <button type="button" class="button schmitke-toggle">Öffnen</button>
                                                        <button type="button" class="button button-link-delete schmitke-remove">Löschen</button>
                                                    </div>
                                                </div>

                                                <div class="schmitke-model-body">
                                                    <div class="schmitke-model-grid">
                                                        <label>Key (Slug)
                                                            <input type="text" name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][<?php echo $i; ?>][key]"
                                                                   value="<?php echo esc_attr($option['key'] ?? ''); ?>">
                                                        </label>
                                                        <label>Titel
                                                            <input type="text" name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][<?php echo $i; ?>][title]"
                                                                   value="<?php echo esc_attr($option['title'] ?? ''); ?>">
                                                        </label>
                                                        <label>Untertitel
                                                            <input type="text" name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][<?php echo $i; ?>][subtitle]"
                                                                   value="<?php echo esc_attr($option['subtitle'] ?? ''); ?>">
                                                        </label>
                                                        <label>Beschreibung
                                                            <textarea name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][<?php echo $i; ?>][description]" rows="3"><?php echo esc_textarea($option['description'] ?? ''); ?></textarea>
                                                        </label>

                                                        <div class="schmitke-image-field">
                                                            <label>Bild (WP-Mediathek)</label>
                                                            <div class="schmitke-image-row">
                                                                <img class="schmitke-image-preview" src="<?php echo esc_url($imagePreview); ?>" alt="" />
                                                                <div class="schmitke-image-buttons">
                                                                    <button type="button" class="button schmitke-pick-image">Bild wählen</button>
                                                                    <button type="button" class="button schmitke-clear-image">Entfernen</button>
                                                                </div>
                                                            </div>
                                                            <input type="hidden" class="schmitke-image-id"
                                                                   name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][<?php echo $i; ?>][imageId]"
                                                                   value="<?php echo esc_attr($option['imageId'] ?? 0); ?>">
                                                            <input type="hidden" class="schmitke-image-url"
                                                                   name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][<?php echo $i; ?>][image]"
                                                                   value="<?php echo esc_attr($option['image'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <p><button type="button" class="button button-secondary schmitke-add-option"
                                               id="schmitke-window-add-option-<?php echo esc_attr($groupKey); ?>"
                                               data-group="<?php echo esc_attr($groupKey); ?>"
                                               data-template="#schmitke-window-option-template-<?php echo esc_attr($groupKey); ?>">+ Element hinzufügen</button></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <script type="text/template" id="schmitke-model-template">
            <div class="schmitke-card schmitke-model-card">
                <div class="schmitke-model-head">
                    <div>
                        <strong class="schmitke-model-title">Neues Modell</strong>
                        <div class="schmitke-model-sub"></div>
                    </div>
                    <div class="schmitke-model-actions">
                        <button type="button" class="button schmitke-toggle">Öffnen</button>
                        <button type="button" class="button button-link-delete schmitke-remove">Löschen</button>
                    </div>
                </div>
                <div class="schmitke-model-body">
                    <div class="schmitke-model-grid">
                        <label>Familie
                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][__i__][family]" value="">
                        </label>
                        <label>Modellname
                            <input type="text" name="<?php echo esc_attr($opt); ?>[models][__i__][name]" value="">
                        </label>
                        <label>Material Default
                            <select name="<?php echo esc_attr($opt); ?>[models][__i__][materialDefault]">
                                <?php foreach (($data['materials'] ?? []) as $mat): ?>
                                    <option value="<?php echo esc_attr($mat['code']); ?>"><?php echo esc_html($mat['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Rahmen Default
                            <select name="<?php echo esc_attr($opt); ?>[models][__i__][frameDefault]">
                                <?php foreach (($data['frames'] ?? []) as $frame): ?>
                                    <option value="<?php echo esc_attr($frame['code']); ?>"><?php echo esc_html($frame['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Glas Default
                            <select name="<?php echo esc_attr($opt); ?>[models][__i__][glassDefault]">
                                <?php foreach (($data['glass'] ?? []) as $glass): ?>
                                    <option value="<?php echo esc_attr($glass['code']); ?>"><?php echo esc_html($glass['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="schmitke-image-field">
                            <label>Bild (WP-Mediathek)</label>
                            <div class="schmitke-image-row">
                                <img class="schmitke-image-preview" src="" alt="" />
                                <div class="schmitke-image-buttons">
                                    <button type="button" class="button schmitke-pick-image">Bild wählen</button>
                                    <button type="button" class="button schmitke-clear-image">Entfernen</button>
                                </div>
                            </div>
                            <input type="hidden" class="schmitke-image-id"
                                   name="<?php echo esc_attr($opt); ?>[models][__i__][imageId]" value="0">
                            <input type="hidden" class="schmitke-image-url"
                                   name="<?php echo esc_attr($opt); ?>[models][__i__][image]" value="">
                        </div>
                    </div>
                </div>
            </div>
        </script>
        <?php foreach ($optionGroups as $groupKey => $groupLabel): ?>
            <script type="text/template" id="schmitke-window-option-template-<?php echo esc_attr($groupKey); ?>">
                <div class="schmitke-card schmitke-option-card">
                    <div class="schmitke-card-head">
                        <div>
                            <strong class="schmitke-card-title"><?php echo esc_html($groupLabel); ?> Option</strong>
                            <div class="schmitke-card-sub"></div>
                        </div>
                        <div class="schmitke-model-actions">
                            <button type="button" class="button schmitke-toggle">Öffnen</button>
                            <button type="button" class="button button-link-delete schmitke-remove">Löschen</button>
                        </div>
                    </div>
                    <div class="schmitke-model-body">
                        <div class="schmitke-model-grid">
                            <label>Key (Slug)
                                <input type="text" name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][__i__][key]" value="">
                            </label>
                            <label>Titel
                                <input type="text" name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][__i__][title]" value="">
                            </label>
                            <label>Untertitel
                                <input type="text" name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][__i__][subtitle]" value="">
                            </label>
                            <label>Beschreibung
                                <textarea name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][__i__][description]" rows="3"></textarea>
                            </label>

                            <div class="schmitke-image-field">
                                <label>Bild (WP-Mediathek)</label>
                                <div class="schmitke-image-row">
                                    <img class="schmitke-image-preview" src="" alt="" />
                                    <div class="schmitke-image-buttons">
                                        <button type="button" class="button schmitke-pick-image">Bild wählen</button>
                                        <button type="button" class="button schmitke-clear-image">Entfernen</button>
                                    </div>
                                </div>
                                <input type="hidden" class="schmitke-image-id"
                                       name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][__i__][imageId]" value="0">
                                <input type="hidden" class="schmitke-image-url"
                                       name="<?php echo esc_attr($opt); ?>[options][<?php echo esc_attr($groupKey); ?>][__i__][image]" value="">
                            </div>
                        </div>
                    </div>
                </div>
            </script>
        <?php endforeach; ?>
        <?php
    }

    public function shortcode($atts) {
        wp_enqueue_style('schmitke-windows-configurator');
        wp_enqueue_script('schmitke-windows-configurator');
        return '<div id="schmitke-window-configurator"></div>';
    }

    private function render_windows_nav_tabs($active) {
        $tabs = [
            'legacy' => __('Konfigurator v1', 'schmitke-doors'),
            'v2' => __('Konfigurator v2 (Fenster)', 'schmitke-doors'),
        ];
        $base = admin_url('options-general.php?page=schmitke-windows-configurator');
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $class = 'nav-tab'.($active === $key ? ' nav-tab-active' : '');
            $url = add_query_arg('tab', $key, $base);
            echo '<a class="'.esc_attr($class).'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
        }
        echo '</h2>';
    }

    private function render_windows_v2_admin() {
        $settings = Schmitke_Configurator_Settings_V2::get_settings();
        $opt = Schmitke_Configurator_Settings_V2::OPTION_KEY;
        ?>
        <div class="wrap schmitke-admin">
            <h1><?php esc_html_e('Schmitke Fenster Konfigurator – v2', 'schmitke-doors'); ?></h1>
            <?php $this->render_windows_nav_tabs('v2'); ?>
            <div class="notice notice-info"><p><?php printf(esc_html__('Frontend uses v2 data: %s', 'schmitke-doors'), !empty($settings['elements']) ? esc_html__('yes', 'schmitke-doors') : esc_html__('no', 'schmitke-doors')); ?></p></div>
            <form method="post" action="options.php" id="schmitke-configurator-v2-form">
                <?php settings_fields('schmitke_configurator_settings_v2_group'); ?>
                <input type="hidden" id="schmitke-configurator-v2-input" name="<?php echo esc_attr($opt); ?>" value="<?php echo esc_attr(wp_json_encode($settings)); ?>" />

                <div id="schmitke-configurator-v2-app" class="schmitke-v2-admin" data-option-key="<?php echo esc_attr($opt); ?>">
                    <p><?php esc_html_e('Lade und bearbeite Elemente, Optionen und Regeln direkt in der Oberfläche.', 'schmitke-doors'); ?></p>
                </div>

                <h2><?php esc_html_e('JSON Fallback', 'schmitke-doors'); ?></h2>
                <p class="description"><?php esc_html_e('Falls benötigt, kann das Settings-JSON hier manuell editiert werden.', 'schmitke-doors'); ?></p>
                <textarea id="schmitke-configurator-v2-json" class="large-text code" rows="8"><?php echo esc_textarea(wp_json_encode($settings, JSON_PRETTY_PRINT)); ?></textarea>

                <?php submit_button(__('Einstellungen speichern', 'schmitke-doors')); ?>
            </form>
        </div>
        <?php
    }
}

new Schmitke_Windows_Configurator();
