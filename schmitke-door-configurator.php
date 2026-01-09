<?php
/**
 * Plugin Name: Schmitke Fenster Konfigurator – v2
 * Description: Fenster-Konfigurator als Shortcode mit zentraler V2-Admin-Verwaltung, WP-Mediathek und PDF-Zusammenfassung.
 * Version: 0.2.1
 * Author: fymito / Schmitke
 * Text Domain: schmitke-doors
 */

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__).'includes/config-settings-v2.php';

class Schmitke_Windows_Configurator {

    public function __construct() {
        add_action('plugins_loaded', function(){
            load_plugin_textdomain('schmitke-doors', false, dirname(plugin_basename(__FILE__)).'/languages');
        });
        add_action('admin_menu', [$this, 'admin_menu']);
        add_shortcode('schmitke_windows_configurator', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_ajax_schmitke_windows_request_quote', [$this, 'handle_quote_request']);
        add_action('wp_ajax_nopriv_schmitke_windows_request_quote', [$this, 'handle_quote_request']);
    }

    private function resolve_locale(array $settings): string {
        $mode = $settings['global_ui']['locale_mode'] ?? 'wp_locale';
        if ($mode === 'force_de') {
            return 'de';
        }
        if ($mode === 'force_en') {
            return 'en';
        }
        return (substr(get_locale(), 0, 2) === 'de') ? 'de' : 'en';
    }

    public function register_assets() {
        wp_register_style('schmitke-windows-configurator', plugins_url('public/configurator.css', __FILE__), [], '0.2.1');
        wp_register_script('schmitke-windows-configurator', plugins_url('public/configurator-windows.js', __FILE__), [], '0.2.1', true);

        $settings = Schmitke_Configurator_Settings_V2::get_settings();
        if (!empty($settings['options_by_element']) && is_array($settings['options_by_element'])) {
            foreach ($settings['options_by_element'] as &$optionGroup) {
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
        $data = [
            'design' => $settings['design'] ?? [],
            'v2' => $settings,
            'v2_enabled' => !empty($settings['elements']),
            'locale' => $this->resolve_locale($settings),
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('schmitke_windows_quote'),
        ];

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

    public function shortcode(): string {
        if (!wp_style_is('schmitke-windows-configurator', 'registered') || !wp_script_is('schmitke-windows-configurator', 'registered')) {
            $this->register_assets();
        }
        wp_enqueue_style('schmitke-windows-configurator');
        wp_enqueue_script('schmitke-windows-configurator');
        return '<div id="schmitke-window-configurator"></div>';
    }

    public function admin_assets($hook) {
        if ($hook !== 'settings_page_schmitke-windows-configurator') return;
        wp_enqueue_media();
        wp_enqueue_style('schmitke-configurator-admin', plugins_url('admin/admin.css', __FILE__), [], '0.2.1');
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


    private function get_locale_label($value, $fallback = '', $lang = null): string {
        if (!is_array($value)) {
            return $value !== null ? (string) $value : $fallback;
        }
        $lang = $lang ?: ((substr(get_locale(), 0, 2) === 'de') ? 'de' : 'en');
        if (!empty($value[$lang])) return (string) $value[$lang];
        if (!empty($value['de'])) return (string) $value['de'];
        if (!empty($value['en'])) return (string) $value['en'];
        $first = reset($value);
        return $first !== false ? (string) $first : $fallback;
    }

    private function encode_pdf_text(string $text): string {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function decode_image_data_url(string $dataUrl): ?array {
        if (!preg_match('/^data:(image\\/[^;]+);base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }
        $mime = strtolower(trim($matches[1]));
        $data = base64_decode($matches[2], true);
        if ($data === false) return null;

        if ($mime === 'image/jpeg') {
            return ['mime' => $mime, 'data' => $data];
        }

        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($data);
        if (!$image) return null;

        ob_start();
        imagejpeg($image, null, 85);
        $jpegData = ob_get_clean();
        imagedestroy($image);
        if (!$jpegData) return null;
        return ['mime' => 'image/jpeg', 'data' => $jpegData];
    }

    private function pdf_hex_to_rgb(string $hex, array $fallback = [0, 0, 0]): array {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return $fallback;
        }
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private function build_pdf_document(array $blocks, array $images, array $theme = [], array $meta = []): string {
        $pageWidth = 595;
        $pageHeight = 842;
        $margin = 60;
        $lineHeight = 14;
        $imageGap = 8;
        $maxImageWidth = 420;
        $maxImageHeight = 260;
        $minImageWidth = 200;
        $headerHeight = 64;
        $headerPadding = 18;

        $themeDefaults = [
            'primary' => $this->pdf_hex_to_rgb($theme['primaryColor'] ?? '#111111'),
            'accent' => $this->pdf_hex_to_rgb($theme['accentColor'] ?? '#f2f2f2'),
            'text' => $this->pdf_hex_to_rgb($theme['textColor'] ?? '#111111'),
            'border' => $this->pdf_hex_to_rgb($theme['borderColor'] ?? '#e7e7e7'),
        ];

        $mix_color = function(array $base, array $target, float $ratio): array {
            return [
                $base[0] + ($target[0] - $base[0]) * $ratio,
                $base[1] + ($target[1] - $base[1]) * $ratio,
                $base[2] + ($target[2] - $base[2]) * $ratio,
            ];
        };

        $colors = [
            'primary' => $themeDefaults['primary'],
            'accent' => $themeDefaults['accent'],
            'text' => $themeDefaults['text'],
            'border' => $themeDefaults['border'],
            'muted' => $mix_color($themeDefaults['text'], [1, 1, 1], 0.45),
            'white' => [1, 1, 1],
        ];

        $color_string = function(array $color): string {
            return sprintf('%.3f %.3f %.3f', $color[0], $color[1], $color[2]);
        };

        $pages = [[]];
        $pageIndex = 0;
        $cursorY = $pageHeight - $headerHeight - $headerPadding;

        $addHeader = function() use (&$pages, &$pageIndex, $pageWidth, $pageHeight, $headerHeight, $headerPadding, $colors, $color_string, $meta, $margin) {
            $headerY = $pageHeight - $headerHeight;
            $pages[$pageIndex][] = "q " . $color_string($colors['primary']) . " rg 0 {$headerY} {$pageWidth} {$headerHeight} re f Q";

            if (!empty($meta['title'])) {
                $title = $this->encode_pdf_text((string) $meta['title']);
                $titleY = $pageHeight - ($headerPadding + 16);
                $pages[$pageIndex][] = "q " . $color_string($colors['white']) . " rg BT /F2 16 Tf {$margin} {$titleY} Td ({$title}) Tj ET Q";
            }

            if (!empty($meta['subtitle'])) {
                $subtitle = $this->encode_pdf_text((string) $meta['subtitle']);
                $subtitleY = $pageHeight - ($headerPadding + 34);
                $pages[$pageIndex][] = "q " . $color_string($colors['white']) . " rg BT /F1 10 Tf {$margin} {$subtitleY} Td ({$subtitle}) Tj ET Q";
            }

            if (!empty($meta['date'])) {
                $date = $this->encode_pdf_text((string) $meta['date']);
                $fontSize = 9;
                $estimatedWidth = strlen((string) $meta['date']) * $fontSize * 0.5;
                $dateX = max($margin, $pageWidth - $margin - $estimatedWidth);
                $dateY = $pageHeight - ($headerPadding + 16);
                $pages[$pageIndex][] = "q " . $color_string($colors['white']) . " rg BT /F1 {$fontSize} Tf {$dateX} {$dateY} Td ({$date}) Tj ET Q";
            }
        };

        $addPage = function() use (&$pages, &$pageIndex, &$cursorY, $pageHeight, $headerHeight, $headerPadding, $addHeader) {
            $pages[] = [];
            $pageIndex++;
            $addHeader();
            $cursorY = $pageHeight - $headerHeight - $headerPadding;
        };

        $addTextLine = function($text, $x = null, $font = 'F1', $fontSize = 11, $color = null, $lineHeightOverride = null) use (&$pages, &$pageIndex, &$cursorY, $lineHeight, $margin, $addPage, $color_string, $colors) {
            if ($text === '') {
                $cursorY -= $lineHeight;
                return;
            }
            $lineHeightUse = $lineHeightOverride ?? $lineHeight;
            if ($cursorY - $lineHeightUse < $margin) {
                $addPage();
            }
            $xPos = $x === null ? $margin : $x;
            $useColor = $color ?? $colors['text'];
            $pages[$pageIndex][] = "q " . $color_string($useColor) . " rg BT /{$font} {$fontSize} Tf {$xPos} {$cursorY} Td ({$text}) Tj ET Q";
            $cursorY -= $lineHeightUse;
        };

        $ensureSpace = function($height) use (&$cursorY, $margin, $addPage) {
            if ($cursorY - $height < $margin) {
                $addPage();
            }
        };

        $addDivider = function() use (&$pages, &$pageIndex, &$cursorY, $pageWidth, $margin, $color_string, $colors, $ensureSpace) {
            $ensureSpace(6);
            $lineY = $cursorY - 4;
            $pages[$pageIndex][] = "q " . $color_string($colors['border']) . " RG 0.6 w {$margin} {$lineY} m " . ($pageWidth - $margin) . " {$lineY} l S Q";
            $cursorY -= 6;
        };

        $addHeader();
        $cursorY = $pageHeight - $headerHeight - $headerPadding;

        foreach ($blocks as $block) {
            if ($block['type'] === 'text') {
                $text = $this->encode_pdf_text($block['text'] ?? '');
                $rawText = $block['text'] ?? '';
                $xPos = null;
                if (is_string($rawText)) {
                    $trimmed = ltrim($rawText);
                    if (strpos($trimmed, '- ') === 0) {
                        $bulletText = '• ' . substr($trimmed, 2);
                        $text = $this->encode_pdf_text($bulletText);
                        $xPos = $margin + 12;
                    }
                }
                $addTextLine($text, $xPos, 'F1', 10.5, $colors['text'], 13);
            } elseif ($block['type'] === 'section') {
                $cursorY -= 6;
                $text = $this->encode_pdf_text($block['text'] ?? '');
                $addTextLine($text, null, 'F2', 12, $colors['primary'], 16);
                $addDivider();
            } elseif ($block['type'] === 'subsection') {
                $cursorY -= 4;
                $text = $this->encode_pdf_text($block['text'] ?? '');
                $addTextLine($text, null, 'F2', 11, $colors['text'], 14);
                $cursorY -= 2;
            } elseif ($block['type'] === 'spacer') {
                $height = max(0, intval($block['height'] ?? $lineHeight));
                $ensureSpace($height);
                $cursorY -= $height;
            } elseif ($block['type'] === 'page_break') {
                $addPage();
            } elseif ($block['type'] === 'image') {
                $imageName = $block['image_name'] ?? '';
                $width = $block['width'] ?? 0;
                $height = $block['height'] ?? 0;
                if (!$imageName || $width <= 0 || $height <= 0) {
                    continue;
                }
                $scale = min($maxImageWidth / $width, $maxImageHeight / $height);
                if ($scale > 1) {
                    $scale = min($scale, 1.5);
                }
                if ($width < $minImageWidth) {
                    $scale = max($scale, min($minImageWidth / $width, $maxImageWidth / $width, $maxImageHeight / $height, 1.5));
                }
                $displayWidth = $width * $scale;
                $displayHeight = $height * $scale;

                $captionLines = $block['caption'] ?? [];
                $captionHeight = count($captionLines) * $lineHeight;
                $blockHeight = $displayHeight + $captionHeight + $imageGap;
                if ($cursorY - $blockHeight < $margin) {
                    $addPage();
                }
                $x = $margin;
                $y = $cursorY - $displayHeight;
                $pages[$pageIndex][] = "q {$displayWidth} 0 0 {$displayHeight} {$x} {$y} cm /{$imageName} Do Q";
                $cursorY = $y - $imageGap;
                foreach ($captionLines as $captionLine) {
                    $addTextLine($this->encode_pdf_text($captionLine), $margin + 12, 'F1', 9, $colors['muted'], 12);
                }
                $cursorY -= 4;
            }
        }

        $pageCount = count($pages);
        $imageCount = count($images);

        $catalogObjNum = 1;
        $pagesObjNum = 2;
        $pageObjStart = 3;
        $fontObjNum = $pageObjStart + $pageCount;
        $imageObjStart = $fontObjNum + 2;
        $contentObjStart = $imageObjStart + $imageCount;

        $objects = [];
        $objects[$catalogObjNum] = "<< /Type /Catalog /Pages {$pagesObjNum} 0 R >>";
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($pageObjStart + $i) . " 0 R";
        }
        $objects[$pagesObjNum] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount} >>";

        $objects[$fontObjNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontObjNum + 1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($images as $idx => $image) {
            $objNum = $imageObjStart + $idx;
            $objects[$objNum] = "<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($image['data']) . " >>\nstream\n{$image['data']}\nendstream";
        }

        for ($i = 0; $i < $pageCount; $i++) {
            $content = implode("\n", $pages[$i]);
            $contentObjNum = $contentObjStart + $i;
            $objects[$contentObjNum] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        }

        $resource = "<< /Font << /F1 {$fontObjNum} 0 R /F2 " . ($fontObjNum + 1) . " 0 R >>";
        if ($imageCount) {
            $xObjects = [];
            for ($i = 0; $i < $imageCount; $i++) {
                $xObjects[] = "/Im" . ($i + 1) . " " . ($imageObjStart + $i) . " 0 R";
            }
            $resource .= " /XObject << " . implode(' ', $xObjects) . " >>";
        }
        $resource .= " >>";

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjNum = $pageObjStart + $i;
            $contentObjNum = $contentObjStart + $i;
            $objects[$pageObjNum] = "<< /Type /Page /Parent {$pagesObjNum} 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources {$resource} /Contents {$contentObjNum} 0 R >>";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $objIndex => $obj) {
            $offsets[$objIndex] = strlen($pdf);
            $pdf .= "{$objIndex} 0 obj\n{$obj}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogObjNum} 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    public function handle_quote_request() {
        check_ajax_referer('schmitke_windows_quote', 'nonce');

        $payloadRaw = wp_unslash($_POST['payload'] ?? '');
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            wp_send_json_error(['message' => 'Ungültige Anfrage.'], 400);
        }

        $contact = $payload['contact'] ?? [];
        $contactName = sanitize_text_field($contact['name'] ?? '');
        $contactEmail = sanitize_email($contact['email'] ?? '');
        $contactPhone = sanitize_text_field($contact['phone'] ?? '');
        $contactAddress = sanitize_text_field($contact['address'] ?? '');
        $contactMessage = sanitize_textarea_field($contact['message'] ?? '');

        if (empty($contactName) || empty($contactEmail)) {
            wp_send_json_error(['message' => 'Bitte Name und E-Mail angeben.'], 422);
        }

        $positions = [];
        if (!empty($payload['positions']) && is_array($payload['positions'])) {
            foreach ($payload['positions'] as $pos) {
                if (!is_array($pos)) continue;
                $name = sanitize_text_field($pos['name'] ?? '');
                $summary = [];
                if (!empty($pos['summary']) && is_array($pos['summary'])) {
                    foreach ($pos['summary'] as $line) {
                        $summary[] = sanitize_text_field($line);
                    }
                }
                $uploads = [];
                if (!empty($pos['uploads']) && is_array($pos['uploads'])) {
                    foreach ($pos['uploads'] as $upload) {
                        if (!is_array($upload)) continue;
                        $uploads[] = [
                            'label' => sanitize_text_field($upload['label'] ?? ''),
                            'filename' => sanitize_text_field($upload['filename'] ?? ''),
                            'note' => sanitize_textarea_field($upload['note'] ?? ''),
                            'data' => sanitize_text_field($upload['data'] ?? ''),
                        ];
                    }
                }

                if ($name !== '' || !empty($summary) || !empty($uploads)) {
                    $positions[] = [
                        'name' => $name,
                        'summary' => $summary,
                        'uploads' => $uploads,
                    ];
                }
            }
        }

        $v2Settings = Schmitke_Configurator_Settings_V2::get_settings();
        $to = sanitize_email($v2Settings['email_to'] ?? '');
        if (empty($to)) {
            wp_send_json_error(['message' => 'Empfängeradresse fehlt.'], 500);
        }

        $missingPositionsMessage = $this->get_locale_label(
            $v2Settings['messages']['missing_positions'] ?? [],
            'Bitte mindestens eine Position hinzufügen.',
            $this->resolve_locale($v2Settings)
        );

        if (empty($positions)) {
            wp_send_json_error(['message' => $missingPositionsMessage], 422);
        }

        $pdfMeta = [
            'title' => 'Fenster-Konfigurator Angebotsanfrage',
            'subtitle' => 'Anfragezusammenfassung',
            'date' => date_i18n('d.m.Y H:i'),
        ];

        $blocks = [
            ['type' => 'section', 'text' => 'Kontakt'],
            ['type' => 'text', 'text' => 'Name: ' . $contactName],
            ['type' => 'text', 'text' => 'E-Mail: ' . $contactEmail],
        ];
        if ($contactPhone) $blocks[] = ['type' => 'text', 'text' => 'Telefon: ' . $contactPhone];
        if ($contactAddress) $blocks[] = ['type' => 'text', 'text' => 'Adresse: ' . $contactAddress];
        if ($contactMessage) {
            $blocks[] = ['type' => 'spacer', 'height' => 10];
            $blocks[] = ['type' => 'section', 'text' => 'Nachricht'];
            $blocks[] = ['type' => 'text', 'text' => $contactMessage];
        }

        $images = [];
        $blocks[] = ['type' => 'spacer', 'height' => 14];
        $blocks[] = ['type' => 'page_break'];
        $blocks[] = ['type' => 'section', 'text' => 'Gespeicherte Positionen'];
        foreach ($positions as $index => $pos) {
            if ($index > 0) {
                $blocks[] = ['type' => 'page_break'];
                $blocks[] = ['type' => 'section', 'text' => 'Gespeicherte Positionen'];
            }
            $title = 'Position ' . ($index + 1);
            if (!empty($pos['name'])) {
                $title .= ' - ' . $pos['name'];
            }
            $blocks[] = ['type' => 'subsection', 'text' => $title];
            foreach ($pos['summary'] as $line) {
                $blocks[] = ['type' => 'text', 'text' => '- ' . $line];
            }
            foreach ($pos['uploads'] as $upload) {
                if (empty($upload['data'])) continue;
                $decoded = $this->decode_image_data_url($upload['data']);
                if (!$decoded) continue;
                $size = @getimagesizefromstring($decoded['data']);
                if (!$size || empty($size[0]) || empty($size[1])) continue;
                $images[] = [
                    'width' => $size[0],
                    'height' => $size[1],
                    'data' => $decoded['data'],
                ];
                $imageName = 'Im' . count($images);
                if (!empty($upload['label'])) {
                    $blocks[] = ['type' => 'text', 'text' => 'Foto: ' . $upload['label']];
                }
                $caption = [];
                if (!empty($upload['note'])) {
                    $caption[] = 'Notiz: ' . $upload['note'];
                }
                if (!empty($upload['filename'])) {
                    $caption[] = 'Datei: ' . $upload['filename'];
                }
                $blocks[] = [
                    'type' => 'image',
                    'image_name' => $imageName,
                    'width' => $size[0],
                    'height' => $size[1],
                    'caption' => $caption,
                ];
            }
            $blocks[] = ['type' => 'spacer', 'height' => 6];
        }

        $pdfContent = $this->build_pdf_document($blocks, $images, $data['design'] ?? [], $pdfMeta);
        $tmpFile = wp_tempnam('schmitke-windows-offer');
        if (!$tmpFile) {
            wp_send_json_error(['message' => 'PDF konnte nicht erstellt werden.'], 500);
        }
        $bytesWritten = file_put_contents($tmpFile, $pdfContent);
        if ($bytesWritten === false) {
            @unlink($tmpFile);
            wp_send_json_error(['message' => 'PDF konnte nicht erstellt werden.'], 500);
        }

        $pdfFile = $tmpFile . '.pdf';
        $attachmentPath = $tmpFile;
        if (@rename($tmpFile, $pdfFile)) {
            $attachmentPath = $pdfFile;
        }

        $subject = 'Angebotsanfrage Fenster-Konfigurator';
        $bodyLines = [
            'Neue Angebotsanfrage aus dem Fenster-Konfigurator.',
            '',
            'Kontakt:',
            'Name: ' . $contactName,
            'E-Mail: ' . $contactEmail,
        ];
        if ($contactPhone) $bodyLines[] = 'Telefon: ' . $contactPhone;
        if ($contactAddress) $bodyLines[] = 'Adresse: ' . $contactAddress;
        if ($contactMessage) {
            $bodyLines[] = '';
            $bodyLines[] = 'Nachricht:';
            $bodyLines[] = $contactMessage;
        }
        $bodyLines[] = '';
        $bodyLines[] = 'Die PDF-Zusammenfassung ist angehängt.';

        $sent = wp_mail($to, $subject, implode("\n", $bodyLines), [], [$attachmentPath]);
        @unlink($attachmentPath);

        if (!$sent) {
            wp_send_json_error(['message' => 'E-Mail konnte nicht versendet werden.'], 500);
        }

        wp_send_json_success(['reset' => true]);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $this->render_windows_v2_admin();
    }

    private function render_windows_v2_admin() {
        $settings = Schmitke_Configurator_Settings_V2::get_settings();
        $opt = Schmitke_Configurator_Settings_V2::OPTION_KEY;
        ?>
        <div class="wrap schmitke-admin">
            <h1><?php esc_html_e('Schmitke Fenster Konfigurator – v2', 'schmitke-doors'); ?></h1>
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
