<?php
/**
 * Plugin Name: Schmitke Türen Konfigurator (MVP) – v2.1
 * Description: Türen-Configurator als Shortcode mit übersichtlicher Admin-Verwaltung, WP-Mediathek für Bilder und Design-Optionen.
 * Version: 0.2.1
 * Author: fymito / Schmitke
 * Text Domain: schmitke-doors
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/schmitke-door-configurator.php';

new Schmitke_Doors_Configurator_V21();
