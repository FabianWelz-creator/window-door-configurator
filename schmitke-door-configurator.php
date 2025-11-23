<?php
/**
 * Core plugin logic for Schmitke Türen Konfigurator (MVP) – v2.1.
 */

if (!defined('ABSPATH')) exit;

class Schmitke_Doors_Configurator_V21 {
    const VERSION = '0.2.1';
    const OPT_KEY = 'schmitke_doors_configurator_data_v21';

    public function __construct() {
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
                ]
            ]
        ];
    }

    public function register_assets() {
        wp_register_style('schmitke-doors-configurator', plugins_url('public/configurator.css', __FILE__), [], self::VERSION);
        wp_register_script('schmitke-doors-configurator', plugins_url('public/configurator.js', __FILE__), [], self::VERSION, true);

        $data = $this->get_config_data();
        if (!empty($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as &$m) {
                if (empty($m['image']) && !empty($m['imageId'])) {
                    $url = wp_get_attachment_image_url(intval($m['imageId']), 'large');
                    if ($url) $m['image'] = $url;
                }
            }
        }
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
        wp_enqueue_script('schmitke-doors-admin', plugins_url('admin/admin.js', __FILE__), ['jquery','wp-color-picker'], self::VERSION, true);
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
        if (empty($clean['email_to'])) {
            $clean['email_to'] = $defaults['email_to'];
        }

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
            if ($code || $label) {
                $clean['frames'][] = [
                    'code' => $code,
                    'label' => $label,
                ];
            }
        }
        if (empty($clean['frames'])) $clean['frames'] = $defaults['frames'];

        $clean['extras'] = [];
        foreach (($input['extras'] ?? $defaults['extras']) as $ex) {
            if (!is_array($ex)) continue;
            $code = sanitize_text_field($ex['code'] ?? '');
            $label = sanitize_text_field($ex['label'] ?? '');
            if ($code || $label) {
                $clean['extras'][] = [
                    'code' => $code,
                    'label' => $label,
                ];
            }
        }
        if (empty($clean['extras'])) $clean['extras'] = $defaults['extras'];

        $clean['rules'] = $this->sanitize_rules($input['rules'] ?? $defaults['rules'], $defaults['rules']);
        return $clean;
    }

    public static function parse_int_list($raw) {
        $parts = preg_split('/[\s,;]+/', (string)$raw);
        return array_values(array_filter(array_map('intval', $parts)));
    }

    private function sanitize_rules($raw, $defaults) {
        $clean = ['constructionLabels' => []];
        $labels = $raw['constructionLabels'] ?? $defaults['constructionLabels'] ?? [];
        if (is_array($labels)) {
            foreach ($labels as $key => $label) {
                $clean['constructionLabels'][sanitize_key($key)] = sanitize_text_field($label);
            }
        }
        if (empty($clean['constructionLabels'])) {
            $clean['constructionLabels'] = $defaults['constructionLabels'] ?? [];
        }
        return $clean;
    }

    private function get_config_data() {
        $raw = get_option(self::OPT_KEY);
        if (!$raw) return self::default_data();
        return $this->sanitize_data($raw);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $data = $this->get_config_data();
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
                            <div class="schmitke-model-card">
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
            <div class="schmitke-model-card">
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
