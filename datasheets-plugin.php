<?php

/**
 * Plugin Name: Makepeace Google Sheets Data Viewer
 * Plugin URI: https://makepeacecorp.com
 * Description: Plugin para visualización de hoja de Google Sheets.
 * Version: 1.0
 * Author: Jose Ma. Makepeace
 * Author URI: https://makepeacecorp.com
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; 
}

class GoogleSheetsDataViewer {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_shortcode('google_sheet_data', array($this, 'display_sheet_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function add_plugin_page() {
        add_options_page(
            'Google Sheets Settings', 
            'Google Sheets', 
            'manage_options', 
            'google-sheets-settings', 
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('google_sheets_options');
        ?>
        <div class="wrap">
            <h1>Google Sheets Settings</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('google_sheets_options_group');
                do_settings_sections('google-sheets-settings');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'google_sheets_options_group',
            'google_sheets_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'google_sheets_setting_section',
            'Google Sheets Settings',
            array($this, 'section_info'),
            'google-sheets-settings'
        );

        add_settings_field(
            'spreadsheet_id', 
            'Spreadsheet ID', 
            array($this, 'spreadsheet_id_callback'), 
            'google-sheets-settings', 
            'google_sheets_setting_section'
        );
    }

    public function sanitize($input) {
        $sanitary_values = array();
        if (isset($input['spreadsheet_id'])) {
            $sanitary_values['spreadsheet_id'] = sanitize_text_field($input['spreadsheet_id']);
        }
        return $sanitary_values;
    }

    public function section_info() {
        print 'Enter your Google Sheets settings below:';
    }

    public function spreadsheet_id_callback() {
        printf(
            '<input type="text" id="spreadsheet_id" name="google_sheets_options[spreadsheet_id]" value="%s" class="regular-text google-sheets-input" style="width: 100%%; max-width: 400px;" maxlength="600"/>',
            isset($this->options['spreadsheet_id']) ? esc_attr($this->options['spreadsheet_id']) : ''
        );
        echo '<p class="description">Enter the ID of the public Google Sheets spreadsheet.</p>';
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'google-sheets-data-viewer-style',
            plugin_dir_url(__FILE__) . 'css/style.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'css/style.css')
        );
    }

    public function display_sheet_data($atts) {
        $this->enqueue_styles();

        $options = get_option('google_sheets_options');
        
        $data = $this->get_sheet_data($options['spreadsheet_id']);
        
        if (is_wp_error($data)) {
            return $this->format_error_message($data->get_error_message());
        }
        
        $output = '<div class="google-sheets-data-container">';
        $output .= '<div class="table-responsive">';
        $output .= '<table class="google-sheets-data">';
        foreach ($data as $index => $row) {
            $output .= $index === 0 ? '<thead><tr>' : '<tr>';
            foreach ($row as $cell_index => $cell) {
                $cell_class = $index === 0 ? 'header-cell' : 'data-cell';
                $output .= $index === 0 ? "<th class='$cell_class'>" : "<td class='$cell_class'>";
                $output .= $index === 0 ? "<span class='header-content'>" : "<span class='cell-content'>";
                $output .= esc_html($cell);
                $output .= '</span>';
                $output .= $index === 0 ? '</th>' : '</td>';
            }
            $output .= $index === 0 ? '</tr></thead><tbody>' : '</tr>';
        }
        $output .= '</tbody></table>';
        $output .= '</div>'; 
        $output .= '</div>';
        
        return $output;
    }

    private function format_error_message($message) {
        return '<div class="google-sheets-error">
                    <h3>Error al cargar los datos de Google Sheets</h3>
                    <p>' . esc_html($message) . '</p>
                    <p>Por favor, verifica la configuración del plugin y asegúrate de que la hoja de cálculo sea pública.</p>
                </div>';
    }

    private function get_sheet_data($spreadsheet_id) {
        if (empty($spreadsheet_id)) {
            return new WP_Error('empty_id', 'El ID de la hoja de cálculo no está configurado.');
        }

        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/pub?output=csv";

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'No se pudo obtener los datos de la hoja de cálculo. Error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return new WP_Error('empty_response', 'La respuesta de Google Sheets está vacía. Asegúrate de que la hoja de cálculo sea pública.');
        }

        if (strpos($body, '<!DOCTYPE html>') !== false) {
            return new WP_Error('invalid_response', 'La hoja de cálculo no está configurada correctamente como pública o la URL es incorrecta. Por favor, verifica la configuración de tu hoja de Google Sheets.');
        }

        $data = $this->parse_csv($body);

        if (empty($data)) {
            return new WP_Error('parse_error', 'No se pudieron procesar los datos de la hoja de cálculo. Asegúrate de que la hoja contenga datos válidos.');
        }

        return $data;
    }

    private function parse_csv($csv_string) {
        $lines = explode("\n", trim($csv_string));
        $data = array();
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (!empty(array_filter($row))) {  // Ignorar filas vacías
                $data[] = $row;
            }
        }
        return $data;
    }
}

$google_sheets_data_viewer = new GoogleSheetsDataViewer();