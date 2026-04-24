<?php

if (! defined('ABSPATH')) {
    exit;
}

final class KR_Forms_Plugin
{
    private static $instance = null;

    private $forms_option = 'kr_forms_forms';
    private $settings_option = 'kr_forms_email_settings';
    private $legacy_design_option = 'kr_forms_design_settings';
    private $old_forms_option = 'formulare_forms';
    private $old_settings_option = 'formulare_email_settings';
    private $old_legacy_design_option = 'formulare_design_settings';

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate()
    {
        $plugin = self::instance();
        $plugin->bootstrap_storage();
        $plugin->create_security_log_table();
        $plugin->create_request_log_table();
    }

    private function __construct()
    {
        add_action('init', array($this, 'bootstrap_storage'), 1);
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_post_kr_forms_save_form', array($this, 'handle_save_form'));
        add_action('admin_post_kr_forms_delete_form', array($this, 'handle_delete_form'));
        add_action('admin_post_kr_forms_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_kr_forms_send_smtp_test', array($this, 'handle_send_smtp_test'));
        add_action('admin_post_kr_forms_clear_security_log', array($this, 'handle_clear_security_log'));
        add_action('admin_post_kr_forms_clear_request_log', array($this, 'handle_clear_request_log'));
        add_action('admin_post_nopriv_kr_forms_captcha', array($this, 'handle_captcha_image'));
        add_action('admin_post_kr_forms_captcha', array($this, 'handle_captcha_image'));
        add_action('admin_post_nopriv_kr_forms_submit', array($this, 'handle_form_submission'));
        add_action('admin_post_kr_forms_submit', array($this, 'handle_form_submission'));
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));
        add_shortcode('kr-forms', array($this, 'render_shortcode'));
    }

    public function bootstrap_storage()
    {
        if (get_option($this->settings_option) === false) {
            $legacy_settings = get_option($this->old_settings_option, false);
            update_option(
                $this->settings_option,
                is_array($legacy_settings) ? wp_parse_args($legacy_settings, $this->default_settings()) : $this->default_settings(),
                true
            );
        }

        if (get_option($this->forms_option) === false) {
            $legacy_forms = get_option($this->old_forms_option, false);
            update_option(
                $this->forms_option,
                is_array($legacy_forms) ? $legacy_forms : $this->default_forms(),
                true
            );
        }

        if (get_option($this->legacy_design_option) === false) {
            $legacy_design = get_option($this->old_legacy_design_option, false);
            update_option(
                $this->legacy_design_option,
                is_array($legacy_design) ? wp_parse_args($legacy_design, $this->default_design_settings()) : $this->default_design_settings(),
                true
            );
        }
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'KR-Forms',
            'KR-Forms',
            'manage_options',
            'kr-forms',
            array($this, 'render_forms_page'),
            'dashicons-feedback',
            56
        );

        add_submenu_page(
            'kr-forms',
            'Formular bearbeiten',
            'Formular bearbeiten',
            'manage_options',
            'kr-forms-editor',
            array($this, 'render_form_editor_page')
        );

        add_submenu_page(
            'kr-forms',
            'E-Mail-Einstellungen',
            'E-Mail-Einstellungen',
            'manage_options',
            'kr-forms-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'kr-forms',
            'Allgemeines Protokoll',
            'Allgemeines Protokoll',
            'manage_options',
            'kr-forms-request-log',
            array($this, 'render_request_log_page')
        );

        add_submenu_page(
            'kr-forms',
            'Sicherheitsprotokoll',
            'Sicherheitsprotokoll',
            'manage_options',
            'kr-forms-security-log',
            array($this, 'render_security_log_page')
        );
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'kr-forms') === false) {
            return;
        }

        wp_enqueue_style(
            'kr-forms-admin',
            KR_FORMS_PLUGIN_URL . 'assets/admin.css',
            array(),
            KR_FORMS_VERSION
        );
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'kr-forms-frontend',
            KR_FORMS_PLUGIN_URL . 'assets/frontend.css',
            array(),
            KR_FORMS_VERSION
        );

    }

    public function render_forms_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'kr-forms'));
        }

        $forms = $this->get_forms();
        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        ?>
        <div class="wrap kr-forms-admin">
            <h1>KR-Forms</h1>
            <?php $this->render_admin_notice(); ?>

            <div class="kr-forms-card">
                <div class="kr-forms-toolbar">
                    <div>
                        <h2>Vorhandene Formulare</h2>
                        <p>Erstelle Kontakt-, Anfrage- oder Widerrufsformulare und nutze den Shortcode direkt in Seiten oder Beiträgen.</p>
                    </div>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=kr-forms-editor')); ?>">Neues Formular anlegen</a>
                </div>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Shortcode</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($forms)) : ?>
                            <tr>
                                <td colspan="3">Noch keine Formulare vorhanden.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($forms as $saved_form) : ?>
                                <tr>
                                    <td><?php echo esc_html($saved_form['name']); ?></td>
                                    <td><code>[kr-forms id="<?php echo esc_attr($saved_form['id']); ?>"]</code></td>
                                    <td class="kr-forms-actions">
                                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=kr-forms-editor&edit=' . rawurlencode($saved_form['id']))); ?>">Bearbeiten</a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Formular wirklich löschen?');">
                                            <?php wp_nonce_field('kr_forms_delete_form'); ?>
                                            <input type="hidden" name="action" value="kr_forms_delete_form">
                                            <input type="hidden" name="form_id" value="<?php echo esc_attr($saved_form['id']); ?>">
                                            <button type="submit" class="button button-link-delete">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_form_editor_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'kr-forms'));
        }

        $edit_id = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        $form = $this->get_form($edit_id);

        if (! $form) {
            $form = $this->new_form();
        }

        ?>
        <div class="wrap kr-forms-admin kr-forms-editor-wrap">
            <h1><?php echo $edit_id ? 'Formular bearbeiten' : 'Neues Formular'; ?></h1>
            <?php $this->render_admin_notice(); ?>

            <div class="kr-forms-card kr-forms-editor-card">
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=kr-forms')); ?>">&larr; Zur Formularübersicht</a></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('kr_forms_save_form'); ?>
                    <input type="hidden" name="action" value="kr_forms_save_form">
                    <input type="hidden" name="form_id" value="<?php echo esc_attr($form['id']); ?>">

                    <div class="kr-forms-editor-layout">
                        <div class="kr-forms-editor-main">
                            <h3>Allgemein</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="kr-forms-name">Name</label></th>
                                    <td><input id="kr-forms-name" name="name" type="text" class="regular-text" required value="<?php echo esc_attr($form['name']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="kr-forms-success">Erfolgsmeldung</label></th>
                                    <td><input id="kr-forms-success" name="success_message" type="text" class="regular-text" value="<?php echo esc_attr($form['success_message']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="kr-forms-captcha-enabled">Eigenes Captcha</label></th>
                                    <td>
                                        <label>
                                            <input id="kr-forms-captcha-enabled" name="captcha_enabled" type="checkbox" value="1" <?php checked(! empty($form['captcha_enabled'])); ?>>
                                            Eigenes Bild-Captcha vor dem Absenden anzeigen
                                        </label>
                                    </td>
                                </tr>
                            </table>

                            <h3>Felder</h3>
                            <p>Die Feldnamen werden intern genutzt. Verwende einfache Namen wie <code>email</code>, <code>nachricht</code> oder <code>telefon</code>.</p>
                            <table class="widefat striped kr-forms-builder-table">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>Feldname</th>
                                        <th>Typ</th>
                                        <th>Pflicht</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="kr-forms-builder-body">
                                    <?php foreach ($form['fields'] as $index => $field) : ?>
                                        <?php $this->render_field_row($field, $index); ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <p>
                                <button type="button" class="button" id="kr-forms-add-field">Feld hinzufügen</button>
                            </p>

                            <h3>E-Mail</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="kr-forms-subject">E-Mail-Betreff</label></th>
                                    <td><input id="kr-forms-subject" name="email_subject" type="text" class="regular-text" value="<?php echo esc_attr($form['email_subject']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="customer_confirmation_enabled">Bestätigung an Kunden</label></th>
                                    <td>
                                        <label>
                                            <input id="customer_confirmation_enabled" name="customer_confirmation_enabled" type="checkbox" value="1" <?php checked(! empty($form['customer_confirmation_enabled'])); ?>>
                                            Nach erfolgreichem Versand eine Zusammenfassung an den Kunden senden
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="kr-forms-template">E-Mail-Text</label></th>
                                    <td>
                                        <textarea id="kr-forms-template" name="email_template" rows="10" class="large-text code"><?php echo esc_textarea($form['email_template']); ?></textarea>
                                        <p class="description">Verfügbare Platzhalter: <code>{form_name}</code>, <code>{page_url}</code>, <code>{submitted_at}</code>, <code>{all_fields}</code>, <code>{field:name}</code>.</p>
                                    </td>
                                </tr>
                            </table>

                            <h3>Design</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="style_text_color">Textfarbe</label></th>
                                    <td><input id="style_text_color" name="design[style_text_color]" type="color" value="<?php echo esc_attr($form['design']['style_text_color']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_label_color">Label-Farbe</label></th>
                                    <td><input id="style_label_color" name="design[style_label_color]" type="color" value="<?php echo esc_attr($form['design']['style_label_color']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_background_mode">Formular-Hintergrund</label></th>
                                    <td>
                                        <select id="style_background_mode" name="design[style_background_mode]">
                                            <option value="solid" <?php selected($form['design']['style_background_mode'], 'solid'); ?>>Farbe verwenden</option>
                                            <option value="transparent" <?php selected($form['design']['style_background_mode'], 'transparent'); ?>>Transparent</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_background_color">Hintergrundfarbe</label></th>
                                    <td><input id="style_background_color" name="design[style_background_color]" type="color" value="<?php echo esc_attr($form['design']['style_background_color']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_field_background">Feld-Hintergrund</label></th>
                                    <td>
                                        <input id="style_field_background" name="design[style_field_background]" type="color" value="<?php echo esc_attr($form['design']['style_field_background']); ?>">
                                        <label>
                                            <input name="design[style_field_background_mode]" type="checkbox" value="transparent" <?php checked($form['design']['style_field_background_mode'], 'transparent'); ?>>
                                            Transparent
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_border_color">Rahmenfarbe</label></th>
                                    <td><input id="style_border_color" name="design[style_border_color]" type="color" value="<?php echo esc_attr($form['design']['style_border_color']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_button_background">Button-Hintergrund</label></th>
                                    <td><input id="style_button_background" name="design[style_button_background]" type="color" value="<?php echo esc_attr($form['design']['style_button_background']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_button_text">Button-Textfarbe</label></th>
                                    <td><input id="style_button_text" name="design[style_button_text]" type="color" value="<?php echo esc_attr($form['design']['style_button_text']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_button_shape">Button-Form</label></th>
                                    <td>
                                        <select id="style_button_shape" name="design[style_button_shape]">
                                            <option value="round" <?php selected($form['design']['style_button_shape'], 'round'); ?>>Rund</option>
                                            <option value="square" <?php selected($form['design']['style_button_shape'], 'square'); ?>>Eckig</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_success_background">Erfolgsmeldung Hintergrund</label></th>
                                    <td>
                                        <input id="style_success_background" name="design[style_success_background]" type="color" value="<?php echo esc_attr($form['design']['style_success_background']); ?>">
                                        <label>
                                            <input name="design[style_success_background_mode]" type="checkbox" value="transparent" <?php checked($form['design']['style_success_background_mode'], 'transparent'); ?>>
                                            Transparent
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_success_text">Erfolgsmeldung Text</label></th>
                                    <td><input id="style_success_text" name="design[style_success_text]" type="color" value="<?php echo esc_attr($form['design']['style_success_text']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_error_background">Fehlermeldung Hintergrund</label></th>
                                    <td>
                                        <input id="style_error_background" name="design[style_error_background]" type="color" value="<?php echo esc_attr($form['design']['style_error_background']); ?>">
                                        <label>
                                            <input name="design[style_error_background_mode]" type="checkbox" value="transparent" <?php checked($form['design']['style_error_background_mode'], 'transparent'); ?>>
                                            Transparent
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_error_text">Fehlermeldung Text</label></th>
                                    <td><input id="style_error_text" name="design[style_error_text]" type="color" value="<?php echo esc_attr($form['design']['style_error_text']); ?>"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="style_border_radius">Abrundung in Pixel</label></th>
                                    <td><input id="style_border_radius" name="design[style_border_radius]" type="number" min="0" max="40" value="<?php echo esc_attr($form['design']['style_border_radius']); ?>"></td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-primary">Formular speichern</button>
                                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=kr-forms')); ?>">Zur Übersicht</a>
                            </p>
                        </div>

                        <aside class="kr-forms-preview-panel">
                            <div class="kr-forms-preview-header">
                                <div>
                                    <h3>Vorschau</h3>
                                    <p class="description">Die Vorschau aktualisiert sich direkt beim Bearbeiten.</p>
                                </div>
                                <label class="kr-forms-preview-toggle">
                                    <input id="kr-forms-preview-enabled" type="checkbox" checked>
                                    Vorschau anzeigen
                                </label>
                            </div>
                            <div class="kr-forms-preview-controls" id="kr-forms-preview-controls">
                                <label for="kr-forms-preview-page-background">Seitenhintergrund</label>
                                <input id="kr-forms-preview-page-background" name="editor_preview_background" type="color" value="<?php echo esc_attr($settings['editor_preview_background']); ?>">
                            </div>
                            <?php $this->render_editor_preview($form); ?>
                        </aside>
                    </div>
                </form>
            </div>
        </div>

        <script type="text/html" id="tmpl-kr-forms-field-row">
            <?php $this->render_field_row($this->default_field(), '{{INDEX}}'); ?>
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const addButton = document.getElementById('kr-forms-add-field');
                const tbody = document.getElementById('kr-forms-builder-body');
                const formEditor = addButton ? addButton.closest('form') : null;
                const previewFrame = document.getElementById('kr-forms-preview-frame');
                const previewEnabled = document.getElementById('kr-forms-preview-enabled');
                const previewControls = document.getElementById('kr-forms-preview-controls');
                const previewPanel = document.querySelector('.kr-forms-preview-panel');
                const previewPageBackground = document.getElementById('kr-forms-preview-page-background');
                let index = tbody.querySelectorAll('tr').length;

                if (!addButton || !tbody || !formEditor || !previewFrame || !previewEnabled || !previewControls || !previewPanel || !previewPageBackground) {
                    return;
                }

                const escapeHtml = function (value) {
                    return String(value)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                };

                const sanitizePreviewLabel = function (value) {
                    const template = document.createElement('template');
                    template.innerHTML = String(value || '');

                    const allowedTags = new Set(['A']);
                    const allowedAttributes = {
                        A: new Set(['href', 'title', 'target', 'rel']),
                    };

                    const sanitizeNode = function (node) {
                        Array.from(node.children).forEach(function (child) {
                            if (!allowedTags.has(child.tagName)) {
                                child.replaceWith(document.createTextNode(child.textContent || ''));
                                return;
                            }

                            Array.from(child.attributes).forEach(function (attribute) {
                                if (!allowedAttributes[child.tagName].has(attribute.name.toLowerCase())) {
                                    child.removeAttribute(attribute.name);
                                }
                            });

                            const href = child.getAttribute('href');
                            if (href && !/^(https?:|mailto:|tel:|\/|#)/i.test(href)) {
                                child.removeAttribute('href');
                            }

                            sanitizeNode(child);
                        });
                    };

                    sanitizeNode(template.content);

                    return template.innerHTML || escapeHtml(value || 'Feld');
                };

                const getFieldValue = function (name, fallback = '') {
                    const input = formEditor.querySelector('[name="' + name + '"]');
                    return input ? input.value : fallback;
                };

                const isChecked = function (name) {
                    const input = formEditor.querySelector('[name="' + name + '"]');
                    return !!(input && input.checked);
                };

                const getBackgroundValue = function (modeName, colorName) {
                    return isChecked(modeName) || getFieldValue(modeName) === 'transparent'
                        ? 'transparent'
                        : getFieldValue(colorName);
                };

                const previewCss = <?php echo wp_json_encode($this->get_frontend_preview_css()); ?>;

                const buildPreviewField = function (label, type, required) {
                    const marker = required ? '<span class="kr-forms-required">*</span>' : '';
                    const safeLabel = sanitizePreviewLabel(label || 'Feld');
                    if (type === 'textarea') {
                        return '<p class="kr-forms-field"><label>' + safeLabel + ' ' + marker + '</label><textarea disabled></textarea></p>';
                    }
                    if (type === 'checkbox') {
                        return '<p class="kr-forms-field"><label>' + safeLabel + ' ' + marker + '</label><input type="checkbox" disabled></p>';
                    }

                    return '<p class="kr-forms-field"><label>' + safeLabel + ' ' + marker + '</label><input type="' + escapeHtml(type || 'text') + '" disabled value=""></p>';
                };

                const buildPreviewDocument = function (fieldsHtml) {
                    const styleVars = [
                        '--kr-forms-text-color:' + getFieldValue('design[style_text_color]', '#0f172a'),
                        '--kr-forms-label-color:' + getFieldValue('design[style_label_color]', '#0f172a'),
                        '--kr-forms-background:' + (getFieldValue('design[style_background_mode]') === 'transparent' ? 'transparent' : getFieldValue('design[style_background_color]', '#f8fafc')),
                        '--kr-forms-field-background:' + getBackgroundValue('design[style_field_background_mode]', 'design[style_field_background]'),
                        '--kr-forms-border-color:' + getFieldValue('design[style_border_color]', '#c7d0db'),
                        '--kr-forms-button-background:' + getFieldValue('design[style_button_background]', '#0f766e'),
                        '--kr-forms-button-text:' + getFieldValue('design[style_button_text]', '#ffffff'),
                        '--kr-forms-button-radius:' + (getFieldValue('design[style_button_shape]', 'round') === 'square' ? '0px' : '999px'),
                        '--kr-forms-success-background:' + getBackgroundValue('design[style_success_background_mode]', 'design[style_success_background]'),
                        '--kr-forms-success-text:' + getFieldValue('design[style_success_text]', '#14532d'),
                        '--kr-forms-error-background:' + getBackgroundValue('design[style_error_background_mode]', 'design[style_error_background]'),
                        '--kr-forms-error-text:' + getFieldValue('design[style_error_text]', '#7f1d1d'),
                        '--kr-forms-radius:' + getFieldValue('design[style_border_radius]', '14') + 'px'
                    ].join(';');

                    const captchaHtml = isChecked('captcha_enabled')
                        ? '<p class="kr-forms-field"><label>Sicherheitscode <span class="kr-forms-required">*</span></label><input type="text" disabled value=""><div class="kr-forms-captcha-placeholder">CAPTCHA</div><span class="kr-forms-captcha-question">Bitte die Zeichen aus dem Bild eingeben.</span></p>'
                        : '';

                    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><style>' +
                        previewCss +
                        'body{margin:0;padding:16px;background:' + escapeHtml(previewPageBackground.value || '#f6f7f7') + ';font-family:Arial,sans-serif;}' +
                        '.kr-forms-preview-shell{max-width:760px;}' +
                        '.kr-forms-form{pointer-events:none;}' +
                        '.kr-forms-form input,.kr-forms-form textarea,.kr-forms-form button{opacity:1;}' +
                        '.kr-forms-captcha-placeholder{display:block;margin-top:10px;padding:12px 14px;border:1px solid var(--kr-forms-border-color);border-radius:calc(var(--kr-forms-radius) - 4px);background:repeating-linear-gradient(135deg,#e5e7eb,#e5e7eb 8px,#f9fafb 8px,#f9fafb 16px);font-weight:700;letter-spacing:0.4em;text-align:center;}' +
                        '</style></head><body><div class="kr-forms-preview-shell"><div class="kr-forms-form-wrapper" style="' + styleVars + '"><div class="kr-forms-notice kr-forms-notice-success">' +
                        escapeHtml(getFieldValue('success_message', 'Vielen Dank. Deine Anfrage wurde erfolgreich gesendet.')) +
                        '</div><div class="kr-forms-form">' +
                        fieldsHtml +
                        captchaHtml +
                        '<button type="button" class="kr-forms-submit" disabled>Absenden</button></div></div></div></body></html>';
                };

                const updatePreview = function () {
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const fieldsHtml = rows.map(function (row) {
                        const textInputs = row.querySelectorAll('input[type="text"]');
                        const label = textInputs[0] ? textInputs[0].value : '';
                        const type = row.querySelector('select') ? row.querySelector('select').value : 'text';
                        const required = row.querySelector('input[type="checkbox"]') ? row.querySelector('input[type="checkbox"]').checked : false;
                        return buildPreviewField(label, type, required);
                    }).join('');

                    previewFrame.srcdoc = buildPreviewDocument(fieldsHtml || buildPreviewField('Beispiel-Feld', 'text', false));
                };

                const updatePreviewVisibility = function () {
                    const visible = previewEnabled.checked;
                    previewFrame.hidden = !visible;
                    previewControls.hidden = !visible;
                    previewPanel.classList.toggle('is-collapsed', !visible);
                };

                addButton.addEventListener('click', function () {
                    const template = document.getElementById('tmpl-kr-forms-field-row').innerHTML.replaceAll('{{INDEX}}', index);
                    tbody.insertAdjacentHTML('beforeend', template);
                    index += 1;
                    updatePreview();
                });

                tbody.addEventListener('click', function (event) {
                    if (!event.target.classList.contains('kr-forms-remove-field')) {
                        return;
                    }

                    const row = event.target.closest('tr');
                    if (row) {
                        row.remove();
                        updatePreview();
                    }
                });

                formEditor.addEventListener('input', updatePreview);
                formEditor.addEventListener('change', updatePreview);
                previewEnabled.addEventListener('change', updatePreviewVisibility);

                previewPageBackground.addEventListener('input', function () {
                    updatePreview();
                });
                previewPageBackground.addEventListener('change', function () {
                    persistPreviewBackground();
                    updatePreview();
                });
                updatePreviewVisibility();
                updatePreview();
            });
        </script>
        <?php
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'kr-forms'));
        }

        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        ?>
        <div class="wrap kr-forms-admin">
            <h1>E-Mail-Einstellungen</h1>
            <?php $this->render_admin_notice(); ?>

            <div class="kr-forms-card kr-forms-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('kr_forms_save_settings'); ?>
                    <input type="hidden" name="action" value="kr_forms_save_settings">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="recipient_email">Empfänger-E-Mail</label></th>
                            <td>
                                <input id="recipient_email" name="recipient_email" type="email" class="regular-text" value="<?php echo esc_attr($settings['recipient_email']); ?>">
                                <p class="description">Leer lassen, um die WordPress-Administratoradresse zu verwenden.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_name">Absendername</label></th>
                            <td><input id="from_name" name="from_name" type="text" class="regular-text" value="<?php echo esc_attr($settings['from_name']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_email">Absender-E-Mail</label></th>
                            <td><input id="from_email" name="from_email" type="email" class="regular-text" value="<?php echo esc_attr($settings['from_email']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="reply_to_mode">Reply-To</label></th>
                            <td>
                                <select id="reply_to_mode" name="reply_to_mode">
                                    <option value="sender" <?php selected($settings['reply_to_mode'], 'sender'); ?>>Absender aus Formular verwenden</option>
                                    <option value="site" <?php selected($settings['reply_to_mode'], 'site'); ?>>Plugin-Absender verwenden</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <h2>SMTP</h2>
                    <p>Diese Einstellungen werden als Fallback genutzt, wenn kein separates SMTP-Plugin aktiv ist.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="smtp_enabled">Eigenes SMTP aktivieren</label></th>
                            <td>
                                <label>
                                    <input id="smtp_enabled" name="smtp_enabled" type="checkbox" value="1" <?php checked(! empty($settings['smtp_enabled'])); ?>>
                                    SMTP über dieses Plugin konfigurieren
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_host">SMTP-Host</label></th>
                            <td><input id="smtp_host" name="smtp_host" type="text" class="regular-text" value="<?php echo esc_attr($settings['smtp_host']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_port">SMTP-Port</label></th>
                            <td><input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?php echo esc_attr($settings['smtp_port']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_encryption">Verschlüsselung</label></th>
                            <td>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="" <?php selected($settings['smtp_encryption'], ''); ?>>Keine</option>
                                    <option value="tls" <?php selected($settings['smtp_encryption'], 'tls'); ?>>TLS</option>
                                    <option value="ssl" <?php selected($settings['smtp_encryption'], 'ssl'); ?>>SSL</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_auth">SMTP-Authentifizierung</label></th>
                            <td>
                                <label>
                                    <input id="smtp_auth" name="smtp_auth" type="checkbox" value="1" <?php checked(! empty($settings['smtp_auth'])); ?>>
                                    Benutzername und Passwort verwenden
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_username">SMTP-Benutzername</label></th>
                            <td><input id="smtp_username" name="smtp_username" type="text" class="regular-text" value="<?php echo esc_attr($settings['smtp_username']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_password">SMTP-Passwort</label></th>
                            <td>
                                <input id="smtp_password" name="smtp_password" type="password" class="regular-text" value="">
                                <p class="description">
                                    <?php if ($this->has_external_smtp_password()) : ?>
                                        Aktuell ist zusätzlich ein externes SMTP-Passwort über <code>wp-config.php</code> oder eine Umgebungsvariable gesetzt. Das Feld hier bleibt optional.
                                    <?php elseif (! empty($settings['smtp_password'])) : ?>
                                        Es ist bereits ein gespeichertes Passwort vorhanden. Leer lassen, um es unverändert zu behalten. In der Datenbank wird es verschlüsselt gespeichert.
                                    <?php else : ?>
                                        Das Passwort wird verschlüsselt in der Datenbank gespeichert.
                                    <?php endif; ?>
                                </p>
                                <?php if (! $this->has_external_smtp_password() && ! empty($settings['smtp_password'])) : ?>
                                    <label>
                                        <input name="smtp_password_clear" type="checkbox" value="1">
                                        Gespeichertes SMTP-Passwort löschen
                                    </label>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_timeout">Timeout in Sekunden</label></th>
                            <td>
                                <input id="smtp_timeout" name="smtp_timeout" type="number" min="5" max="120" value="<?php echo esc_attr($settings['smtp_timeout']); ?>">
                                <p class="description">
                                    <?php if ($this->has_external_smtp_plugin()) : ?>
                                        Es wurde bereits ein SMTP-Plugin erkannt. Diese Plugin-SMTP-Einstellungen bleiben inaktiv, bis das andere SMTP-Plugin deaktiviert ist.
                                    <?php else : ?>
                                        Diese SMTP-Einstellungen werden direkt für den Mailversand dieses Plugins verwendet.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2>Sicherheit</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="rate_limit_enabled">Rate-Limiting aktivieren</label></th>
                            <td>
                                <label>
                                    <input id="rate_limit_enabled" name="rate_limit_enabled" type="checkbox" value="1" <?php checked(! empty($settings['rate_limit_enabled'])); ?>>
                                    Absendeversuche pro Formular und IP begrenzen
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rate_limit_max_attempts">Maximale Versuche</label></th>
                            <td><input id="rate_limit_max_attempts" name="rate_limit_max_attempts" type="number" min="1" max="100" value="<?php echo esc_attr($settings['rate_limit_max_attempts']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rate_limit_window_minutes">Zeitraum in Minuten</label></th>
                            <td><input id="rate_limit_window_minutes" name="rate_limit_window_minutes" type="number" min="1" max="1440" value="<?php echo esc_attr($settings['rate_limit_window_minutes']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="security_logging_enabled">Sicherheitsprotokoll aktivieren</label></th>
                            <td>
                                <label>
                                    <input id="security_logging_enabled" name="security_logging_enabled" type="checkbox" value="1" <?php checked(! empty($settings['security_logging_enabled'])); ?>>
                                    Missbrauchsereignisse speichern
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="security_log_limit">Maximale Log-Einträge</label></th>
                            <td><input id="security_log_limit" name="security_log_limit" type="number" min="10" max="1000" value="<?php echo esc_attr($settings['security_log_limit']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="security_alerts_enabled">Alarm-E-Mail aktivieren</label></th>
                            <td>
                                <label>
                                    <input id="security_alerts_enabled" name="security_alerts_enabled" type="checkbox" value="1" <?php checked(! empty($settings['security_alerts_enabled'])); ?>>
                                    Bei Rate-Limit und Bot-Erkennung E-Mail senden
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="security_alert_email">Alarm-E-Mail</label></th>
                            <td>
                                <input id="security_alert_email" name="security_alert_email" type="email" class="regular-text" value="<?php echo esc_attr($settings['security_alert_email']); ?>">
                                <p class="description">Leer lassen, um die Empfänger-E-Mail bzw. Admin-Adresse zu verwenden.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="trusted_proxies">Trusted Proxies</label></th>
                            <td>
                                <textarea id="trusted_proxies" name="trusted_proxies" rows="5" class="large-text code"><?php echo esc_textarea($settings['trusted_proxies']); ?></textarea>
                                <p class="description">Nur wenn <code>REMOTE_ADDR</code> hier passt, werden Proxy-Header wie <code>X-Forwarded-For</code> ausgewertet. Erlaubt sind einzelne IPs, CIDR und Wildcards wie <code>172.19.*.*</code>.</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Einstellungen speichern</button>
                    </p>
                </form>
            </div>

                <div class="kr-forms-card kr-forms-settings-card">
                    <h2>SMTP-Test</h2>
                    <p>Speichere zunächst die SMTP-Einstellungen. Der Testversand verwendet die aktuell gespeicherten Werte.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('kr_forms_send_smtp_test'); ?>
                    <input type="hidden" name="action" value="kr_forms_send_smtp_test">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test_email">Test-E-Mail an</label></th>
                            <td>
                                <input id="test_email" name="test_email" type="email" class="regular-text" value="<?php echo esc_attr($settings['recipient_email'] ?: get_option('admin_email')); ?>">
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-secondary">SMTP-Test senden</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_security_log_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'kr-forms'));
        }

        $entries = $this->get_security_log_entries();
        ?>
        <div class="wrap kr-forms-admin">
            <h1>Sicherheitsprotokoll</h1>
            <?php $this->render_admin_notice(); ?>
            <div class="kr-forms-card kr-forms-settings-card">
                <p>Hier werden blockierte oder auffällige Anfragen protokolliert.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="kr_forms_clear_security_log">
                    <?php wp_nonce_field('kr_forms_clear_security_log'); ?>
                    <p><button type="submit" class="button button-secondary" onclick="return window.confirm('Sicherheitsprotokoll wirklich leeren?');">Sicherheitsprotokoll leeren</button></p>
                </form>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Zeitpunkt</th>
                            <th>Typ</th>
                            <th>Formular</th>
                            <th>IP</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entries)) : ?>
                            <tr>
                                <td colspan="5">Noch keine Einträge vorhanden.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($entries as $entry) : ?>
                                <tr>
                                    <td><?php echo esc_html($entry['timestamp']); ?></td>
                                    <td><?php echo esc_html($entry['type']); ?></td>
                                    <td><?php echo esc_html($entry['form_name']); ?></td>
                                    <td><code><?php echo esc_html($entry['ip']); ?></code></td>
                                    <td><?php echo esc_html($entry['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_request_log_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'kr-forms'));
        }

        $this->create_request_log_table();
        $entries = $this->get_request_log_entries();
        ?>
        <div class="wrap kr-forms-admin">
            <h1>Allgemeines Protokoll</h1>
            <?php $this->render_admin_notice(); ?>
            <div class="kr-forms-card kr-forms-log-card">
                <p>Hier werden alle Formularanfragen inklusive erfolgreicher und fehlgeschlagener Versuche protokolliert.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="kr_forms_clear_request_log">
                    <?php wp_nonce_field('kr_forms_clear_request_log'); ?>
                    <p><button type="submit" class="button button-secondary" onclick="return window.confirm('Allgemeines Protokoll wirklich leeren?');">Allgemeines Protokoll leeren</button></p>
                </form>
                <div class="kr-forms-table-wrap">
                    <table class="widefat striped kr-forms-request-log-table">
                        <thead>
                            <tr>
                                <th>Zeitpunkt</th>
                                <th>Status</th>
                                <th>Formular</th>
                                <th>Seite</th>
                                <th>E-Mail</th>
                                <th>Zusammenfassung</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($entries)) : ?>
                                <tr>
                                    <td colspan="7">Noch keine Einträge vorhanden.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($entries as $entry) : ?>
                                    <tr>
                                        <td><?php echo esc_html($entry['timestamp']); ?></td>
                                        <td><?php echo esc_html($entry['status']); ?></td>
                                        <td><?php echo esc_html($entry['form_name']); ?></td>
                                        <td><code class="kr-forms-log-url"><?php echo esc_html($entry['page_url']); ?></code></td>
                                        <td><?php echo esc_html($entry['email']); ?></td>
                                        <td><pre class="kr-forms-log-summary"><?php echo esc_html($entry['summary']); ?></pre></td>
                                        <td class="kr-forms-log-details"><?php echo esc_html($entry['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_save_form()
    {
        $this->ensure_admin_request('kr_forms_save_form');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $form_id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        $success_message = isset($_POST['success_message']) ? sanitize_text_field(wp_unslash($_POST['success_message'])) : '';
        $email_subject = isset($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '';
        $email_template = isset($_POST['email_template']) ? sanitize_textarea_field(wp_unslash($_POST['email_template'])) : '';

        if ($name === '') {
            $this->redirect_admin('kr-forms', array('message' => 'missing_name'));
        }

        if ($form_id === '') {
            $form_id = $this->generate_form_id($name);
        }

        $fields = $this->sanitize_fields(isset($_POST['fields']) ? wp_unslash($_POST['fields']) : array());

        if (empty($fields)) {
            $fields[] = $this->default_field();
        }

        $forms = $this->get_forms();
        $forms[$form_id] = array(
            'id' => $form_id,
            'name' => $name,
            'success_message' => $success_message ?: 'Vielen Dank. Deine Anfrage wurde erfolgreich gesendet.',
            'email_subject' => $email_subject ?: 'Neue Formularanfrage',
            'email_template' => $email_template ?: $this->default_email_template(),
            'customer_confirmation_enabled' => ! empty($_POST['customer_confirmation_enabled']),
            'captcha_enabled' => ! empty($_POST['captcha_enabled']),
            'fields' => $fields,
            'design' => $this->sanitize_design_settings(isset($_POST['design']) ? wp_unslash($_POST['design']) : array()),
        );

        update_option($this->forms_option, $forms);
        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        $settings['editor_preview_background'] = $this->sanitize_color_setting(
            isset($_POST['editor_preview_background']) ? wp_unslash($_POST['editor_preview_background']) : $settings['editor_preview_background'],
            '#f6f7f7'
        );
        update_option($this->settings_option, $settings);

        $this->redirect_admin('kr-forms-editor', array(
            'message' => 'saved',
            'edit' => $form_id,
        ));
    }

    public function handle_delete_form()
    {
        $this->ensure_admin_request('kr_forms_delete_form');

        $form_id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        $forms = $this->get_forms();

        if ($form_id && isset($forms[$form_id])) {
            unset($forms[$form_id]);
            update_option($this->forms_option, $forms);
        }

        $this->redirect_admin('kr-forms', array('message' => 'deleted'));
    }

    public function handle_save_settings()
    {
        $this->ensure_admin_request('kr_forms_save_settings');

        $existing_settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        $submitted_smtp_password = isset($_POST['smtp_password']) ? sanitize_text_field(wp_unslash($_POST['smtp_password'])) : '';
        $smtp_password = $existing_settings['smtp_password'];

        if (! empty($_POST['smtp_password_clear'])) {
            $smtp_password = '';
        } elseif ($submitted_smtp_password !== '') {
            $smtp_password = $this->encrypt_secret($submitted_smtp_password);
        } elseif ($smtp_password !== '' && ! $this->is_encrypted_secret($smtp_password)) {
            $smtp_password = $this->encrypt_secret($smtp_password);
        }

        $settings = array(
            'recipient_email' => isset($_POST['recipient_email']) ? sanitize_email(wp_unslash($_POST['recipient_email'])) : '',
            'from_name' => isset($_POST['from_name']) ? sanitize_text_field(wp_unslash($_POST['from_name'])) : '',
            'from_email' => isset($_POST['from_email']) ? sanitize_email(wp_unslash($_POST['from_email'])) : '',
            'reply_to_mode' => isset($_POST['reply_to_mode']) && wp_unslash($_POST['reply_to_mode']) === 'site' ? 'site' : 'sender',
            'smtp_enabled' => ! empty($_POST['smtp_enabled']),
            'smtp_host' => isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '',
            'smtp_port' => $this->sanitize_port_setting(isset($_POST['smtp_port']) ? wp_unslash($_POST['smtp_port']) : 587),
            'smtp_encryption' => $this->sanitize_encryption_setting(isset($_POST['smtp_encryption']) ? wp_unslash($_POST['smtp_encryption']) : ''),
            'smtp_auth' => ! empty($_POST['smtp_auth']),
            'smtp_username' => isset($_POST['smtp_username']) ? sanitize_text_field(wp_unslash($_POST['smtp_username'])) : '',
            'smtp_password' => $smtp_password,
            'smtp_timeout' => $this->sanitize_timeout_setting(isset($_POST['smtp_timeout']) ? wp_unslash($_POST['smtp_timeout']) : 20),
            'rate_limit_enabled' => ! empty($_POST['rate_limit_enabled']),
            'rate_limit_max_attempts' => $this->sanitize_rate_limit_attempts(isset($_POST['rate_limit_max_attempts']) ? wp_unslash($_POST['rate_limit_max_attempts']) : 5),
            'rate_limit_window_minutes' => $this->sanitize_rate_limit_window(isset($_POST['rate_limit_window_minutes']) ? wp_unslash($_POST['rate_limit_window_minutes']) : 10),
            'security_logging_enabled' => ! empty($_POST['security_logging_enabled']),
            'security_log_limit' => $this->sanitize_log_limit(isset($_POST['security_log_limit']) ? wp_unslash($_POST['security_log_limit']) : 200),
            'security_alerts_enabled' => ! empty($_POST['security_alerts_enabled']),
            'security_alert_email' => isset($_POST['security_alert_email']) ? sanitize_email(wp_unslash($_POST['security_alert_email'])) : '',
            'trusted_proxies' => $this->sanitize_trusted_proxies_setting(isset($_POST['trusted_proxies']) ? wp_unslash($_POST['trusted_proxies']) : ''),
        );

        update_option($this->settings_option, wp_parse_args($settings, $this->default_settings()));

        $this->redirect_admin('kr-forms-settings', array('message' => 'settings_saved'));
    }

    public function handle_clear_security_log()
    {
        $this->ensure_admin_request('kr_forms_clear_security_log');
        $this->clear_log_table($this->get_security_log_table_name());
        $this->redirect_admin('kr-forms-security-log', array('message' => 'security_log_cleared'));
    }

    public function handle_clear_request_log()
    {
        $this->ensure_admin_request('kr_forms_clear_request_log');
        $this->create_request_log_table();
        $this->clear_log_table($this->get_request_log_table_name());
        $this->redirect_admin('kr-forms-request-log', array('message' => 'request_log_cleared'));
    }

    public function handle_send_smtp_test()
    {
        $this->ensure_admin_request('kr_forms_send_smtp_test');

        $target = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';

        if (! is_email($target)) {
            $this->redirect_admin('kr-forms-settings', array('message' => 'smtp_test_invalid'));
        }

        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        $subject = 'SMTP-Test: KR-Forms';
        $message = implode(
            "\n",
            array(
                'Dies ist eine Test-E-Mail des Plugins "KR-Forms".',
                '',
                'Zeitpunkt: ' . wp_date('d.m.Y H:i:s'),
                'SMTP über Plugin aktiv: ' . (! empty($settings['smtp_enabled']) ? 'Ja' : 'Nein'),
                'Externe SMTP-Erkennung: ' . ($this->has_external_smtp_plugin() ? 'Ja' : 'Nein'),
                'Host: ' . ($settings['smtp_host'] ?: '-'),
                'Port: ' . $settings['smtp_port'],
                'Verschlüsselung: ' . ($settings['smtp_encryption'] ?: 'keine'),
            )
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent = wp_mail($target, $subject, $message, $headers);

        if (! $sent) {
            $this->record_security_event('smtp_test_failed', '', 'SMTP-Test', 'SMTP-Testversand fehlgeschlagen.');
        }

        $this->redirect_admin('kr-forms-settings', array('message' => $sent ? 'smtp_test_sent' : 'smtp_test_failed'));
    }

    public function handle_form_submission()
    {
        $form_id = isset($_POST['form_id']) ? sanitize_key(wp_unslash($_POST['form_id'])) : '';
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $nonce = isset($_POST['_wpnonce']) ? wp_unslash($_POST['_wpnonce']) : '';
        $honeypot = isset($_POST['website']) ? trim((string) wp_unslash($_POST['website'])) : '';
        $posted_email = isset($_POST['kr_forms']['email']) ? sanitize_email(wp_unslash($_POST['kr_forms']['email'])) : '';

        if (! wp_verify_nonce($nonce, 'kr_forms_submit_' . $form_id)) {
            $this->record_request_event('security_error', $form_id, '', $redirect, $posted_email, 'Nonce-Prüfung fehlgeschlagen.');
            $this->record_security_event('security_error', $form_id, '', 'Nonce-Prüfung fehlgeschlagen.', true);
            $this->redirect_submission($redirect, 'security_error', $form_id);
        }

        if ($honeypot !== '') {
            $this->record_request_event('spam_blocked', $form_id, '', $redirect, $posted_email, 'Honeypot-Feld wurde ausgefüllt.');
            $this->record_security_event('honeypot', $form_id, '', 'Honeypot-Feld wurde ausgefüllt.', true);
            $this->redirect_submission($redirect, 'spam_blocked', $form_id);
        }

        $form = $this->get_form($form_id);

        if (! $form) {
            $this->record_request_event('missing_form', $form_id, '', $redirect, $posted_email, 'Formular-ID nicht gefunden.');
            $this->record_security_event('missing_form', $form_id, '', 'Formular-ID nicht gefunden.', true);
            $this->redirect_submission($redirect, 'missing_form', $form_id);
        }

        if (! $this->check_rate_limit($form_id, $form['name'])) {
            $this->record_request_event('rate_limit', $form_id, $form['name'], $redirect, $posted_email, 'Rate-Limit ausgelöst.');
            $this->redirect_submission($redirect, 'rate_limit', $form_id);
        }

        if (! empty($form['captcha_enabled']) && ! $this->validate_captcha_submission($_POST, $form_id)) {
            $this->record_request_event('captcha_error', $form_id, $form['name'], $redirect, $posted_email, 'Captcha wurde nicht korrekt gelöst.');
            $this->record_security_event(
                'captcha_failed',
                $form_id,
                $form['name'],
                'Captcha wurde nicht korrekt gelöst.',
                true
            );
            $this->redirect_submission($redirect, 'captcha_error', $form_id);
        }

        $values = array();
        $errors = array();
        $reply_to = '';

        foreach ($form['fields'] as $field) {
            $field_name = $field['name'];
            $raw_value = isset($_POST['kr_forms'][$field_name]) ? wp_unslash($_POST['kr_forms'][$field_name]) : '';
            $value = is_array($raw_value) ? implode(', ', array_map('sanitize_text_field', $raw_value)) : sanitize_textarea_field($raw_value);

            if ($field['type'] === 'email' && $value !== '') {
                $value = sanitize_email($value);
                if (! is_email($value)) {
                    $errors[] = $field_name;
                } elseif ($reply_to === '') {
                    $reply_to = $value;
                }
            }

            if (! empty($field['required']) && $value === '') {
                $errors[] = $field_name;
            }

            if ($field['type'] === 'checkbox' && $value === '') {
                $value = 'Nein';
            }

            $values[$field_name] = $value;
        }

        if (! empty($errors)) {
            $summary = $this->build_all_fields_text($form, $values);
            $this->record_request_event('validation_error', $form_id, $form['name'], $redirect, $reply_to, 'Formularvalidierung fehlgeschlagen.', $summary);
            $this->record_security_event(
                'validation_error',
                $form_id,
                $form['name'],
                'Formularvalidierung fehlgeschlagen.'
            );
            $this->redirect_submission($redirect, 'validation_error', $form_id);
        }

        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        $recipient = $settings['recipient_email'] ?: get_option('admin_email');
        $from_name = $settings['from_name'] ?: get_bloginfo('name');
        $from_email = $settings['from_email'] ?: get_option('admin_email');
        $subject = $form['email_subject'] ?: 'Neue Formularanfrage';

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );

        if ($settings['reply_to_mode'] === 'sender' && $reply_to !== '') {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $summary = $this->build_all_fields_text($form, $values);
        $message = $this->build_email_message($form, $values, $redirect);
        $sent = wp_mail($recipient, $subject, $message, $headers);

        if (! $sent) {
            $this->record_request_event('mail_error', $form_id, $form['name'], $redirect, $reply_to, 'E-Mail-Versand fehlgeschlagen.', $summary);
            $this->record_security_event(
                'mail_error',
                $form_id,
                $form['name'],
                'E-Mail-Versand fehlgeschlagen.'
            );
            $this->redirect_submission($redirect, 'mail_error', $form_id);
        }

        $success_details = 'Formular erfolgreich versendet.';

        if (! empty($form['customer_confirmation_enabled']) && $reply_to !== '') {
            $confirmation_sent = $this->send_customer_confirmation($form, $values, $redirect, $reply_to, $settings);
            $success_details = $confirmation_sent
                ? 'Formular erfolgreich versendet. Bestätigung an Kunden gesendet.'
                : 'Formular erfolgreich versendet. Bestätigung an Kunden konnte nicht gesendet werden.';
        }

        $this->record_request_event('success', $form_id, $form['name'], $redirect, $reply_to, $success_details, $summary);
        $this->redirect_submission($redirect, 'success', $form_id);
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'id' => '',
            ),
            $atts,
            'kr-forms'
        );

        $form = $this->get_form($atts['id']);

        if (! $form) {
            return '<div class="kr-forms-notice kr-forms-notice-error">Formular nicht gefunden.</div>';
        }

        $status = isset($_GET['kr_forms_status'], $_GET['kr_forms_id'])
            ? sanitize_key(wp_unslash($_GET['kr_forms_status']))
            : '';
        $status_form_id = isset($_GET['kr_forms_id']) ? sanitize_key(wp_unslash($_GET['kr_forms_id'])) : '';

        ob_start();
        ?>
        <?php $captcha = ! empty($form['captcha_enabled']) ? $this->create_captcha_challenge($form['id']) : null; ?>
        <div class="kr-forms-form-wrapper" style="<?php echo esc_attr($this->build_form_style($form)); ?>">
            <?php if ($status_form_id === $form['id']) : ?>
                <?php echo wp_kses_post($this->render_frontend_notice($status, $form)); ?>
            <?php endif; ?>
            <form class="kr-forms-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="kr_forms_submit">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form['id']); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($this->current_url()); ?>">
                <?php wp_nonce_field('kr_forms_submit_' . $form['id']); ?>

                <div class="kr-forms-honeypot" aria-hidden="true">
                    <label for="kr-forms-website-<?php echo esc_attr($form['id']); ?>">Website</label>
                    <input id="kr-forms-website-<?php echo esc_attr($form['id']); ?>" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <?php foreach ($form['fields'] as $field) : ?>
                    <?php $this->render_frontend_field($field, $form['id']); ?>
                <?php endforeach; ?>

                <?php if ($captcha) : ?>
                    <p class="kr-forms-field">
                        <label for="kr-forms-captcha-<?php echo esc_attr($form['id']); ?>">
                            Sicherheitscode
                            <span class="kr-forms-required">*</span>
                        </label>
                        <input id="kr-forms-captcha-<?php echo esc_attr($form['id']); ?>" type="text" name="kr_forms_captcha_answer" required inputmode="text" autocomplete="off" spellcheck="false">
                        <input type="hidden" name="kr_forms_captcha_token" value="<?php echo esc_attr($captcha['token']); ?>">
                        <img class="kr-forms-captcha-image" src="<?php echo esc_url($captcha['image_url']); ?>" alt="Captcha">
                        <span class="kr-forms-captcha-question">Bitte die Zeichen aus dem Bild eingeben.</span>
                    </p>
                <?php endif; ?>

                <button type="submit" class="kr-forms-submit">Absenden</button>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public function configure_phpmailer($phpmailer)
    {
        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());

        if (empty($settings['smtp_enabled']) || $this->has_external_smtp_plugin()) {
            return;
        }

        if ($settings['smtp_host'] === '') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['smtp_host'];
        $phpmailer->Port = (int) $settings['smtp_port'];
        $phpmailer->Timeout = (int) $settings['smtp_timeout'];
        $phpmailer->SMTPAuth = ! empty($settings['smtp_auth']);
        $phpmailer->Username = $settings['smtp_username'];
        $phpmailer->Password = $this->get_smtp_password($settings);
        $phpmailer->SMTPSecure = $settings['smtp_encryption'];

        if (! empty($settings['from_email']) && is_email($settings['from_email'])) {
            $phpmailer->setFrom($settings['from_email'], $settings['from_name'], false);
        }
    }

    private function render_frontend_field($field, $form_id)
    {
        $field_id = 'kr-forms-' . $form_id . '-' . $field['name'];
        $required = ! empty($field['required']);
        ?>
        <p class="kr-forms-field">
            <label for="<?php echo esc_attr($field_id); ?>">
                <?php echo wp_kses($field['label'], $this->get_allowed_label_html()); ?>
                <?php if ($required) : ?>
                    <span class="kr-forms-required">*</span>
                <?php endif; ?>
            </label>
            <?php if ($field['type'] === 'textarea') : ?>
                <textarea id="<?php echo esc_attr($field_id); ?>" name="kr_forms[<?php echo esc_attr($field['name']); ?>]" <?php echo $required ? 'required' : ''; ?>></textarea>
            <?php elseif ($field['type'] === 'checkbox') : ?>
                <input id="<?php echo esc_attr($field_id); ?>" type="checkbox" name="kr_forms[<?php echo esc_attr($field['name']); ?>]" value="Ja" <?php echo $required ? 'required' : ''; ?>>
            <?php else : ?>
                <input id="<?php echo esc_attr($field_id); ?>" type="<?php echo esc_attr($field['type']); ?>" name="kr_forms[<?php echo esc_attr($field['name']); ?>]" <?php echo $required ? 'required' : ''; ?>>
            <?php endif; ?>
        </p>
        <?php
    }

    private function render_field_row($field, $index)
    {
        ?>
        <tr>
            <td><input type="text" name="fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($field['label']); ?>" required></td>
            <td><input type="text" name="fields[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($field['name']); ?>" required></td>
            <td>
                <select name="fields[<?php echo esc_attr($index); ?>][type]">
                    <?php foreach ($this->field_types() as $type) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($field['type'], $type); ?>><?php echo esc_html($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="checkbox" name="fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked(! empty($field['required'])); ?>></td>
            <td><button type="button" class="button button-link-delete kr-forms-remove-field">Entfernen</button></td>
        </tr>
        <?php
    }

    private function render_editor_preview($form)
    {
        ?>
        <div class="kr-forms-preview-surface">
            <iframe id="kr-forms-preview-frame" class="kr-forms-preview-frame" title="Formularvorschau"></iframe>
        </div>
        <?php
    }

    private function get_frontend_preview_css()
    {
        $path = KR_FORMS_PLUGIN_DIR . 'assets/frontend.css';

        if (! file_exists($path) || ! is_readable($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }

    private function render_admin_notice()
    {
        $message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
        $messages = array(
            'saved' => array('updated', 'Formular wurde gespeichert.'),
            'deleted' => array('updated', 'Formular wurde gelöscht.'),
            'settings_saved' => array('updated', 'E-Mail-Einstellungen wurden gespeichert.'),
            'request_log_cleared' => array('updated', 'Allgemeines Protokoll wurde geleert.'),
            'security_log_cleared' => array('updated', 'Sicherheitsprotokoll wurde geleert.'),
            'smtp_test_sent' => array('updated', 'SMTP-Test wurde versendet.'),
            'smtp_test_failed' => array('error', 'SMTP-Test konnte nicht versendet werden.'),
            'smtp_test_invalid' => array('error', 'Bitte eine gültige Test-E-Mail-Adresse angeben.'),
            'missing_name' => array('error', 'Bitte einen Formularnamen angeben.'),
        );

        if (! isset($messages[$message])) {
            return;
        }

        list($class, $text) = $messages[$message];
        printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($text));
    }

    private function render_frontend_notice($status, $form)
    {
        if ($status === 'success') {
            return '<div class="kr-forms-notice kr-forms-notice-success">' . esc_html($form['success_message']) . '</div>';
        }

        $messages = array(
            'validation_error' => 'Bitte prüfe die Eingaben und versuche es erneut.',
            'captcha_error' => 'Der Sicherheitscode wurde nicht korrekt eingegeben.',
            'rate_limit' => 'Zu viele Anfragen. Bitte versuche es später erneut.',
            'mail_error' => 'Die E-Mail konnte nicht gesendet werden.',
            'security_error' => 'Sicherheitsprüfung fehlgeschlagen.',
            'missing_form' => 'Das Formular ist nicht verfügbar.',
            'spam_blocked' => 'Die Anfrage wurde blockiert.',
        );

        if (! isset($messages[$status])) {
            return '';
        }

        return '<div class="kr-forms-notice kr-forms-notice-error">' . esc_html($messages[$status]) . '</div>';
    }

    private function get_forms()
    {
        $forms = get_option($this->forms_option, array());

        if (! is_array($forms)) {
            return array();
        }

        $legacy_design = $this->legacy_design_settings();

        foreach ($forms as $key => $form) {
            $forms[$key] = $this->normalize_form($form, $legacy_design);
        }

        return $forms;
    }

    private function get_form($id)
    {
        $forms = $this->get_forms();
        $id = sanitize_key($id);

        return isset($forms[$id]) ? $forms[$id] : null;
    }

    private function sanitize_fields($raw_fields)
    {
        $fields = array();

        if (! is_array($raw_fields)) {
            return $fields;
        }

        foreach ($raw_fields as $raw_field) {
            $label = isset($raw_field['label']) ? $this->sanitize_field_label($raw_field['label']) : '';
            $name = isset($raw_field['name']) ? sanitize_key($raw_field['name']) : '';
            $type = isset($raw_field['type']) ? sanitize_key($raw_field['type']) : 'text';
            $required = ! empty($raw_field['required']);

            if ($label === '' || $name === '') {
                continue;
            }

            if (! in_array($type, $this->field_types(), true)) {
                $type = 'text';
            }

            $fields[] = array(
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'required' => $required,
            );
        }

        return $fields;
    }

    private function sanitize_design_settings($settings)
    {
        $defaults = $this->default_design_settings();

        if (! is_array($settings)) {
            return $defaults;
        }

        return array(
            'style_text_color' => $this->sanitize_color_setting(isset($settings['style_text_color']) ? $settings['style_text_color'] : $defaults['style_text_color']) ?: $defaults['style_text_color'],
            'style_label_color' => $this->sanitize_color_setting(isset($settings['style_label_color']) ? $settings['style_label_color'] : $defaults['style_label_color']) ?: $defaults['style_label_color'],
            'style_background_mode' => isset($settings['style_background_mode']) && $settings['style_background_mode'] === 'transparent' ? 'transparent' : 'solid',
            'style_background_color' => $this->sanitize_color_setting(isset($settings['style_background_color']) ? $settings['style_background_color'] : $defaults['style_background_color']) ?: $defaults['style_background_color'],
            'style_field_background_mode' => isset($settings['style_field_background_mode']) && $settings['style_field_background_mode'] === 'transparent' ? 'transparent' : 'solid',
            'style_field_background' => $this->sanitize_color_setting(isset($settings['style_field_background']) ? $settings['style_field_background'] : $defaults['style_field_background']) ?: $defaults['style_field_background'],
            'style_border_color' => $this->sanitize_color_setting(isset($settings['style_border_color']) ? $settings['style_border_color'] : $defaults['style_border_color']) ?: $defaults['style_border_color'],
            'style_button_background' => $this->sanitize_color_setting(isset($settings['style_button_background']) ? $settings['style_button_background'] : $defaults['style_button_background']) ?: $defaults['style_button_background'],
            'style_button_text' => $this->sanitize_color_setting(isset($settings['style_button_text']) ? $settings['style_button_text'] : $defaults['style_button_text']) ?: $defaults['style_button_text'],
            'style_button_shape' => $this->sanitize_button_shape_setting(isset($settings['style_button_shape']) ? $settings['style_button_shape'] : $defaults['style_button_shape']),
            'style_success_background_mode' => isset($settings['style_success_background_mode']) && $settings['style_success_background_mode'] === 'transparent' ? 'transparent' : 'solid',
            'style_success_background' => $this->sanitize_color_setting(isset($settings['style_success_background']) ? $settings['style_success_background'] : $defaults['style_success_background']) ?: $defaults['style_success_background'],
            'style_success_text' => $this->sanitize_color_setting(isset($settings['style_success_text']) ? $settings['style_success_text'] : $defaults['style_success_text']) ?: $defaults['style_success_text'],
            'style_error_background_mode' => isset($settings['style_error_background_mode']) && $settings['style_error_background_mode'] === 'transparent' ? 'transparent' : 'solid',
            'style_error_background' => $this->sanitize_color_setting(isset($settings['style_error_background']) ? $settings['style_error_background'] : $defaults['style_error_background']) ?: $defaults['style_error_background'],
            'style_error_text' => $this->sanitize_color_setting(isset($settings['style_error_text']) ? $settings['style_error_text'] : $defaults['style_error_text']) ?: $defaults['style_error_text'],
            'style_border_radius' => $this->sanitize_radius_setting(isset($settings['style_border_radius']) ? $settings['style_border_radius'] : $defaults['style_border_radius']),
        );
    }

    private function ensure_admin_request($nonce_action)
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'kr-forms'));
        }

        check_admin_referer($nonce_action);
    }

    private function redirect_admin($page, $args = array())
    {
        $url = add_query_arg($args, admin_url('admin.php?page=' . $page));
        wp_safe_redirect($url);
        exit;
    }

    private function redirect_submission($redirect, $status, $form_id)
    {
        $url = add_query_arg(
            array(
                'kr_forms_status' => $status,
                'kr_forms_id' => $form_id,
            ),
            $redirect
        );

        wp_safe_redirect($url);
        exit;
    }

    private function current_url()
    {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        return esc_url_raw($scheme . $host . $uri);
    }

    private function generate_form_id($name)
    {
        $base = sanitize_title($name);
        $id = $base ?: 'formular';
        $forms = $this->get_forms();
        $counter = 2;

        while (isset($forms[$id])) {
            $id = $base . '-' . $counter;
            $counter += 1;
        }

        return $id;
    }

    private function field_types()
    {
        return array('text', 'email', 'tel', 'textarea', 'checkbox');
    }

    private function sanitize_color_setting($value)
    {
        $value = sanitize_hex_color($value);

        return $value ?: '';
    }

    private function sanitize_radius_setting($value)
    {
        $radius = absint($value);

        return min($radius, 40);
    }

    private function sanitize_button_shape_setting($value)
    {
        return $value === 'square' ? 'square' : 'round';
    }

    private function sanitize_port_setting($value)
    {
        $port = absint($value);

        if ($port < 1 || $port > 65535) {
            return 587;
        }

        return $port;
    }

    private function sanitize_timeout_setting($value)
    {
        $timeout = absint($value);

        if ($timeout < 5) {
            return 20;
        }

        return min($timeout, 120);
    }

    private function sanitize_rate_limit_attempts($value)
    {
        $attempts = absint($value);

        if ($attempts < 1) {
            return 5;
        }

        return min($attempts, 100);
    }

    private function sanitize_rate_limit_window($value)
    {
        $minutes = absint($value);

        if ($minutes < 1) {
            return 10;
        }

        return min($minutes, 1440);
    }

    private function sanitize_log_limit($value)
    {
        $limit = absint($value);

        if ($limit < 10) {
            return 200;
        }

        return min($limit, 1000);
    }

    private function sanitize_encryption_setting($value)
    {
        $value = sanitize_key($value);

        return in_array($value, array('', 'tls', 'ssl'), true) ? $value : '';
    }

    private function sanitize_trusted_proxies_setting($value)
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $parts = preg_split('/[\r\n,;]+/', $value);

        if (! is_array($parts)) {
            return '';
        }

        $patterns = array();

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $part = preg_replace('/[^0-9A-Fa-f:\.\/\*\%]/', '', $part);

            if ($part === '') {
                continue;
            }

            $patterns[] = $part;
        }

        $patterns = array_values(array_unique($patterns));

        return implode("\n", $patterns);
    }

    private function get_smtp_password($settings)
    {
        if (defined('KR_FORMS_SMTP_PASSWORD')) {
            return (string) KR_FORMS_SMTP_PASSWORD;
        }

        $env_password = getenv('KR_FORMS_SMTP_PASSWORD');
        if ($env_password !== false && $env_password !== '') {
            return (string) $env_password;
        }

        if (empty($settings['smtp_password'])) {
            return '';
        }

        return $this->decrypt_secret((string) $settings['smtp_password']);
    }

    private function has_external_smtp_password()
    {
        if (defined('KR_FORMS_SMTP_PASSWORD')) {
            return true;
        }

        $env_password = getenv('KR_FORMS_SMTP_PASSWORD');

        return $env_password !== false && $env_password !== '';
    }

    private function is_encrypted_secret($value)
    {
        return is_string($value) && strpos($value, 'enc:') === 0;
    }

    private function encrypt_secret($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if ($this->is_encrypted_secret($value)) {
            return $value;
        }

        if (! function_exists('openssl_encrypt')) {
            return $value;
        }

        $key = $this->get_secret_encryption_key();

        if ($key === '') {
            return $value;
        }

        $iv_length = (int) openssl_cipher_iv_length('aes-256-cbc');

        if ($iv_length < 1) {
            return $value;
        }

        try {
            $iv = random_bytes($iv_length);
        } catch (Exception $exception) {
            return $value;
        }

        $ciphertext = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if (! is_string($ciphertext) || $ciphertext === '') {
            return $value;
        }

        return 'enc:' . base64_encode($iv . $ciphertext);
    }

    private function decrypt_secret($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (! $this->is_encrypted_secret($value)) {
            return $value;
        }

        if (! function_exists('openssl_decrypt')) {
            return '';
        }

        $key = $this->get_secret_encryption_key();

        if ($key === '') {
            return '';
        }

        $payload = base64_decode(substr($value, 4), true);
        $iv_length = (int) openssl_cipher_iv_length('aes-256-cbc');

        if (! is_string($payload) || $payload === '' || $iv_length < 1 || strlen($payload) <= $iv_length) {
            return '';
        }

        $iv = substr($payload, 0, $iv_length);
        $ciphertext = substr($payload, $iv_length);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return is_string($plaintext) ? $plaintext : '';
    }

    private function get_secret_encryption_key()
    {
        if (function_exists('wp_salt')) {
            return hash('sha256', wp_salt('auth') . '|' . wp_salt('secure_auth'), true);
        }

        if (defined('AUTH_KEY') && defined('SECURE_AUTH_KEY')) {
            return hash('sha256', AUTH_KEY . '|' . SECURE_AUTH_KEY, true);
        }

        return '';
    }

    private function get_request_ip()
    {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? trim(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $remote_addr = trim($remote_addr, " \t\n\r\0\x0B\"'[]");

        if (! filter_var($remote_addr, FILTER_VALIDATE_IP)) {
            return 'unbekannt';
        }

        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());

        if (! $this->is_trusted_proxy_ip($remote_addr, $settings['trusted_proxies'])) {
            return $remote_addr;
        }

        $candidates = array();
        $server_values = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED',
        );

        foreach ($server_values as $server_key) {
            if (empty($_SERVER[$server_key])) {
                continue;
            }

            $raw_value = wp_unslash($_SERVER[$server_key]);

            if ($server_key === 'HTTP_FORWARDED') {
                $candidates = array_merge($candidates, $this->extract_ips_from_forwarded_header($raw_value));
                continue;
            }

            $candidates = array_merge($candidates, $this->extract_ip_candidates($raw_value));
        }

        $fallback_ip = '';

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate, " \t\n\r\0\x0B\"'[]");

            if (! filter_var($candidate, FILTER_VALIDATE_IP)) {
                continue;
            }

            if ($fallback_ip === '') {
                $fallback_ip = $candidate;
            }

            if ($this->is_public_ip($candidate)) {
                return $candidate;
            }
        }

        return $fallback_ip !== '' ? $fallback_ip : $remote_addr;
    }

    private function extract_ip_candidates($raw_value)
    {
        if (! is_string($raw_value) || $raw_value === '') {
            return array();
        }

        $parts = preg_split('/\s*,\s*/', $raw_value);

        return is_array($parts) ? $parts : array();
    }

    private function extract_ips_from_forwarded_header($raw_value)
    {
        if (! is_string($raw_value) || $raw_value === '') {
            return array();
        }

        preg_match_all('/for=(?:"?\\[?)([^;,\"]+)/i', $raw_value, $matches);

        return ! empty($matches[1]) && is_array($matches[1]) ? $matches[1] : array();
    }

    private function is_trusted_proxy_ip($ip, $patterns_raw)
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $patterns = preg_split('/[\r\n,;]+/', (string) $patterns_raw);

        if (! is_array($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);

            if ($pattern === '') {
                continue;
            }

            if ($this->ip_matches_trusted_proxy_pattern($ip, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function ip_matches_trusted_proxy_pattern($ip, $pattern)
    {
        if ($pattern === $ip) {
            return true;
        }

        if (strpos($pattern, '/') !== false) {
            return $this->ip_matches_cidr($ip, $pattern);
        }

        if (strpos($pattern, '*') !== false || strpos($pattern, '%') !== false) {
            $regex = '/^' . str_replace(array('\*', '\%'), '.*', preg_quote($pattern, '/')) . '$/i';

            return (bool) preg_match($regex, $ip);
        }

        return false;
    }

    private function ip_matches_cidr($ip, $cidr)
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        list($subnet, $mask) = $parts;
        $mask = (int) $mask;
        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);

        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)) {
            return false;
        }

        $max_bits = strlen($ip_bin) * 8;

        if ($mask < 0 || $mask > $max_bits) {
            return false;
        }

        $full_bytes = intdiv($mask, 8);
        $remaining_bits = $mask % 8;

        if ($full_bytes > 0 && substr($ip_bin, 0, $full_bytes) !== substr($subnet_bin, 0, $full_bytes)) {
            return false;
        }

        if ($remaining_bits === 0) {
            return true;
        }

        $mask_byte = (0xFF << (8 - $remaining_bits)) & 0xFF;

        return (ord($ip_bin[$full_bytes]) & $mask_byte) === (ord($subnet_bin[$full_bytes]) & $mask_byte);
    }

    private function is_public_ip($ip)
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function check_rate_limit($form_id, $form_name)
    {
        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());

        if (empty($settings['rate_limit_enabled'])) {
            return true;
        }

        $ip = $this->get_request_ip();
        $window = (int) $settings['rate_limit_window_minutes'] * MINUTE_IN_SECONDS;
        $limit = (int) $settings['rate_limit_max_attempts'];
        $key = 'kr_forms_rate_' . md5($form_id . '|' . $ip);
        $attempts = get_transient($key);
        $attempts = is_array($attempts) ? $attempts : array();
        $now = time();

        $attempts = array_values(array_filter($attempts, static function ($timestamp) use ($now, $window) {
            return is_numeric($timestamp) && (int) $timestamp >= ($now - $window);
        }));

        if (count($attempts) >= $limit) {
            $details = sprintf(
                'Rate-Limit erreicht (%d Versuche in %d Minuten).',
                $limit,
                (int) $settings['rate_limit_window_minutes']
            );
            $this->record_security_event('rate_limit', $form_id, $form_name, $details, true);

            return false;
        }

        $attempts[] = $now;
        set_transient($key, $attempts, $window);

        return true;
    }

    private function record_security_event($type, $form_id, $form_name, $details, $notify = false)
    {
        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());

        if (! empty($settings['security_logging_enabled'])) {
            $this->insert_security_log_entry(array(
                'timestamp' => current_time('mysql'),
                'type' => $type,
                'form_id' => $form_id,
                'form_name' => $form_name ?: '-',
                'ip' => $this->get_request_ip(),
                'details' => $details,
            ));
            $this->trim_security_log_table((int) $settings['security_log_limit']);
        }

        if ($notify && ! empty($settings['security_alerts_enabled'])) {
            $this->send_security_alert($type, $form_id, $form_name, $details);
        }
    }

    private function record_request_event($status, $form_id, $form_name, $page_url, $email, $details, $summary = '')
    {
        $this->create_request_log_table();
        $this->insert_request_log_entry(array(
            'timestamp' => current_time('mysql'),
            'status' => $status,
            'form_id' => $form_id,
            'form_name' => $form_name ?: '-',
            'page_url' => $page_url ?: '',
            'email' => is_email($email) ? $email : '',
            'details' => $details,
            'summary' => $summary !== '' ? $summary : '-',
        ));
        $this->trim_request_log_table(1000);
    }

    private function get_security_log_entries()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT logged_at, event_type, form_id, form_name, ip_address, details
             FROM ' . $this->get_security_log_table_name() . '
             ORDER BY id DESC
             LIMIT 200',
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        return array_map(function ($row) {
            return array(
                'timestamp' => mysql2date('d.m.Y H:i:s', $row['logged_at']),
                'type' => $row['event_type'],
                'form_id' => $row['form_id'],
                'form_name' => $row['form_name'],
                'ip' => $row['ip_address'],
                'details' => $row['details'],
            );
        }, $rows);
    }

    private function get_request_log_entries()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT logged_at, request_status, form_id, form_name, page_url, sender_email, summary_text, details
             FROM ' . $this->get_request_log_table_name() . '
             ORDER BY id DESC
             LIMIT 500',
            ARRAY_A
        );

        if (! is_array($rows)) {
            return array();
        }

        return array_map(function ($row) {
            return array(
                'timestamp' => mysql2date('d.m.Y H:i:s', $row['logged_at']),
                'status' => $row['request_status'],
                'form_id' => $row['form_id'],
                'form_name' => $row['form_name'],
                'page_url' => $row['page_url'],
                'email' => $row['sender_email'],
                'summary' => $row['summary_text'],
                'details' => $row['details'],
            );
        }, $rows);
    }

    private function send_security_alert($type, $form_id, $form_name, $details)
    {
        $settings = wp_parse_args(get_option($this->settings_option, array()), $this->default_settings());
        $recipient = $settings['security_alert_email'] ?: ($settings['recipient_email'] ?: get_option('admin_email'));

        if (! is_email($recipient)) {
            return;
        }

        $ip = $this->get_request_ip();
        $throttle_key = 'kr_forms_alert_' . md5($type . '|' . $form_id . '|' . $ip);

        if (get_transient($throttle_key)) {
            return;
        }

        set_transient($throttle_key, '1', 30 * MINUTE_IN_SECONDS);

        $subject = 'Sicherheitsalarm: KR-Forms';
        $message = implode("\n", array(
            'Es wurde ein Sicherheitsereignis erkannt.',
            '',
            'Typ: ' . $type,
            'Formular: ' . ($form_name ?: $form_id),
            'IP: ' . $ip,
            'Zeitpunkt: ' . wp_date('d.m.Y H:i:s'),
            'Details: ' . $details,
        ));

        wp_mail($recipient, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
    }

    private function build_form_style($form)
    {
        $design = $form['design'];
        $background = $design['style_background_mode'] === 'transparent'
            ? 'transparent'
            : $design['style_background_color'];
        $field_background = $design['style_field_background_mode'] === 'transparent'
            ? 'transparent'
            : $design['style_field_background'];
        $success_background = $design['style_success_background_mode'] === 'transparent'
            ? 'transparent'
            : $design['style_success_background'];
        $error_background = $design['style_error_background_mode'] === 'transparent'
            ? 'transparent'
            : $design['style_error_background'];

        $variables = array(
            '--kr-forms-text-color:' . $design['style_text_color'],
            '--kr-forms-label-color:' . $design['style_label_color'],
            '--kr-forms-background:' . $background,
            '--kr-forms-field-background:' . $field_background,
            '--kr-forms-border-color:' . $design['style_border_color'],
            '--kr-forms-button-background:' . $design['style_button_background'],
            '--kr-forms-button-text:' . $design['style_button_text'],
            '--kr-forms-button-radius:' . ($design['style_button_shape'] === 'square' ? '0px' : '999px'),
            '--kr-forms-success-background:' . $success_background,
            '--kr-forms-success-text:' . $design['style_success_text'],
            '--kr-forms-error-background:' . $error_background,
            '--kr-forms-error-text:' . $design['style_error_text'],
            '--kr-forms-radius:' . (int) $design['style_border_radius'] . 'px',
        );

        return implode(';', $variables);
    }

    private function build_email_message($form, $values, $page_url)
    {
        $replacements = array(
            '{form_name}' => $form['name'],
            '{page_url}' => $page_url,
            '{submitted_at}' => $this->format_submission_date(),
            '{all_fields}' => $this->build_all_fields_text($form, $values),
        );

        foreach ($values as $key => $value) {
            $replacements['{field:' . $key . '}'] = $value !== '' ? $value : '-';
        }

        $message = strtr($form['email_template'], $replacements);

        return trim($message);
    }

    private function send_customer_confirmation($form, $values, $page_url, $recipient, $settings)
    {
        if (! is_email($recipient)) {
            return false;
        }

        $from_name = $settings['from_name'] ?: get_bloginfo('name');
        $from_email = $settings['from_email'] ?: get_option('admin_email');
        $subject = 'Bestätigung: ' . $form['name'];
        $message = implode(
            "\n",
            array(
                'Vielen Dank für deine Anfrage.',
                '',
                'Wir haben die folgenden Angaben erhalten:',
                $this->build_all_fields_text($form, $values),
                '',
                'Gesendet am: ' . $this->format_submission_date(),
                'Seite: ' . $page_url,
            )
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );

        return wp_mail($recipient, $subject, $message, $headers);
    }

    private function build_all_fields_text($form, $values)
    {
        $lines = array();

        foreach ($form['fields'] as $field) {
            $lines[] = sprintf('%s: %s', $this->build_label_text_for_summary($field['label']), isset($values[$field['name']]) && $values[$field['name']] !== '' ? $values[$field['name']] : '-');
        }

        return implode("\n", $lines);
    }

    private function build_label_text_for_summary($label)
    {
        $label = wp_kses((string) $label, $this->get_allowed_label_html());

        $label = preg_replace_callback(
            '/<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            static function ($matches) {
                $link_text = trim(wp_strip_all_tags($matches[3]));
                $href = trim($matches[2]);

                if ($href === '') {
                    return $link_text;
                }

                return sprintf('%s (%s)', $link_text, $href);
            },
            $label
        );

        return trim(wp_strip_all_tags($label));
    }

    private function sanitize_field_label($label)
    {
        return trim(wp_kses((string) $label, $this->get_allowed_label_html()));
    }

    private function get_allowed_label_html()
    {
        return array(
            'a' => array(
                'href' => true,
                'title' => true,
                'target' => true,
                'rel' => true,
            ),
        );
    }

    private function has_external_smtp_plugin()
    {
        $known_classes = array(
            'WPMS_SMTP',
            'EasyWPSMTP',
            'Postman\\WordPressMail\\Main',
            'FluentMail\\App',
        );

        foreach ($known_classes as $class_name) {
            if (class_exists($class_name)) {
                return true;
            }
        }

        $active_plugins = (array) get_option('active_plugins', array());
        $network_plugins = array_keys((array) get_site_option('active_sitewide_plugins', array()));
        $plugin_list = array_merge($active_plugins, $network_plugins);

        foreach ($plugin_list as $plugin_file) {
            if (stripos($plugin_file, 'smtp') !== false || stripos($plugin_file, 'mail-smtp') !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalize_form($form, $legacy_design = array())
    {
        $defaults = $this->new_form();
        $form = wp_parse_args($form, $defaults);
        $form['fields'] = $this->sanitize_fields($form['fields']);
        $form['email_template'] = ! empty($form['email_template']) ? $form['email_template'] : $this->default_email_template();
        $form['customer_confirmation_enabled'] = ! empty($form['customer_confirmation_enabled']);
        $form['captcha_enabled'] = ! empty($form['captcha_enabled']);
        $form['design'] = $this->sanitize_design_settings(wp_parse_args(isset($form['design']) ? $form['design'] : array(), $legacy_design));

        if (empty($form['fields'])) {
            $form['fields'] = $defaults['fields'];
        }

        return $form;
    }

    private function legacy_design_settings()
    {
        $legacy = get_option($this->legacy_design_option, array());

        return $this->sanitize_design_settings(is_array($legacy) ? $legacy : array());
    }

    private function new_form()
    {
        return array(
            'id' => '',
            'name' => '',
            'success_message' => 'Vielen Dank. Deine Anfrage wurde erfolgreich gesendet.',
            'email_subject' => 'Neue Formularanfrage',
            'email_template' => $this->default_email_template(),
            'customer_confirmation_enabled' => false,
            'captcha_enabled' => true,
            'fields' => array(
                array(
                    'label' => 'Dein Name',
                    'name' => 'name',
                    'type' => 'text',
                    'required' => true,
                ),
                array(
                    'label' => 'E-Mail',
                    'name' => 'email',
                    'type' => 'email',
                    'required' => true,
                ),
                array(
                    'label' => 'Nachricht',
                    'name' => 'nachricht',
                    'type' => 'textarea',
                    'required' => true,
                ),
            ),
            'design' => $this->legacy_design_settings(),
        );
    }

    private function default_field()
    {
        return array(
            'label' => '',
            'name' => '',
            'type' => 'text',
            'required' => false,
        );
    }

    private function default_forms()
    {
        $design = $this->legacy_design_settings();

        return array(
            'kontaktformular' => array(
                'id' => 'kontaktformular',
                'name' => 'Kontaktformular',
                'success_message' => 'Vielen Dank. Deine Nachricht wurde übermittelt.',
                'email_subject' => 'Neue Kontaktanfrage',
                'email_template' => $this->default_email_template(),
                'customer_confirmation_enabled' => false,
                'captcha_enabled' => true,
                'design' => $design,
                'fields' => array(
                    array(
                        'label' => 'Name',
                        'name' => 'name',
                        'type' => 'text',
                        'required' => true,
                    ),
                    array(
                        'label' => 'E-Mail',
                        'name' => 'email',
                        'type' => 'email',
                        'required' => true,
                    ),
                    array(
                        'label' => 'Nachricht',
                        'name' => 'nachricht',
                        'type' => 'textarea',
                        'required' => true,
                    ),
                ),
            ),
            'widerrufsformular' => array(
                'id' => 'widerrufsformular',
                'name' => 'Widerrufsformular',
                'success_message' => 'Dein Widerruf wurde versendet.',
                'email_subject' => 'Neuer Widerruf',
                'email_template' => $this->default_email_template(),
                'customer_confirmation_enabled' => false,
                'captcha_enabled' => true,
                'design' => $design,
                'fields' => array(
                    array(
                        'label' => 'Vor- und Nachname',
                        'name' => 'voller_name',
                        'type' => 'text',
                        'required' => true,
                    ),
                    array(
                        'label' => 'E-Mail',
                        'name' => 'email',
                        'type' => 'email',
                        'required' => true,
                    ),
                    array(
                        'label' => 'Bestellnummer',
                        'name' => 'bestellnummer',
                        'type' => 'text',
                        'required' => true,
                    ),
                    array(
                        'label' => 'Widerrufstext',
                        'name' => 'widerruf',
                        'type' => 'textarea',
                        'required' => true,
                    ),
                ),
            ),
        );
    }

    private function default_email_template()
    {
        return implode(
            "\n",
            array(
                'Neue Anfrage über {form_name}',
                '',
                'Gesendet am: {submitted_at}',
                'Seite: {page_url}',
                '',
                '{all_fields}',
            )
        );
    }

    private function format_submission_date()
    {
        return wp_date('d.m.Y H:i:s');
    }

    private function create_captcha_challenge($form_id)
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $answer = '';

        for ($index = 0; $index < 5; $index++) {
            $answer .= $characters[wp_rand(0, strlen($characters) - 1)];
        }

        $token = wp_generate_password(20, false, false);

        set_transient(
            'kr_forms_captcha_' . $token,
            array(
                'form_id' => $form_id,
                'answer' => $answer,
            ),
            30 * MINUTE_IN_SECONDS
        );

        return array(
            'token' => $token,
            'image_url' => add_query_arg(
                array(
                    'action' => 'kr_forms_captcha',
                    'token' => rawurlencode($token),
                ),
                admin_url('admin-post.php')
            ),
        );
    }

    private function validate_captcha_submission($post_data, $form_id)
    {
        $token = isset($post_data['kr_forms_captcha_token']) ? sanitize_text_field(wp_unslash($post_data['kr_forms_captcha_token'])) : '';
        $answer = isset($post_data['kr_forms_captcha_answer']) ? trim((string) wp_unslash($post_data['kr_forms_captcha_answer'])) : '';

        if ($token === '' || $answer === '') {
            return false;
        }

        $stored = get_transient('kr_forms_captcha_' . $token);
        delete_transient('kr_forms_captcha_' . $token);

        if (! is_array($stored) || empty($stored['answer']) || empty($stored['form_id'])) {
            return false;
        }

        if ($stored['form_id'] !== $form_id) {
            return false;
        }

        $normalized_answer = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $answer));

        return hash_equals((string) $stored['answer'], $normalized_answer);
    }

    public function handle_captcha_image()
    {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $stored = get_transient('kr_forms_captcha_' . $token);

        if (! is_array($stored) || empty($stored['answer'])) {
            status_header(404);
            header('Content-Type: image/svg+xml; charset=UTF-8');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="60"><rect width="100%" height="100%" fill="#fee2e2"/><text x="18" y="36" font-size="16" fill="#7f1d1d">Captcha abgelaufen</text></svg>';
            exit;
        }

        header('Content-Type: image/svg+xml; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $this->build_captcha_svg((string) $stored['answer']);
        exit;
    }

    private function build_captcha_svg($answer)
    {
        $width = 180;
        $height = 60;
        $letters = preg_split('//u', $answer, -1, PREG_SPLIT_NO_EMPTY);
        $svg = array();

        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="Captcha">',
            $width,
            $height
        );
        $svg[] = '<rect width="100%" height="100%" fill="#f3f4f6" rx="10" ry="10"/>';

        for ($line = 0; $line < 6; $line++) {
            $svg[] = sprintf(
                '<path d="M0 %1$d C40 %2$d, 90 %3$d, 180 %4$d" stroke="%5$s" stroke-width="1.2" fill="none" opacity="0.45"/>',
                wp_rand(5, 55),
                wp_rand(0, 60),
                wp_rand(0, 60),
                wp_rand(5, 55),
                $this->captcha_palette_color()
            );
        }

        foreach ($letters as $index => $character) {
            $x = 22 + ($index * 30);
            $y = wp_rand(34, 46);
            $rotation = wp_rand(-24, 24);
            $font_size = wp_rand(24, 30);
            $svg[] = sprintf(
                '<text x="%1$d" y="%2$d" font-family="Verdana, Arial, sans-serif" font-size="%3$d" font-weight="700" fill="%4$s" transform="rotate(%5$d %1$d %2$d)">%6$s</text>',
                $x,
                $y,
                $font_size,
                $this->captcha_palette_color(),
                $rotation,
                esc_html($character)
            );
        }

        for ($dot = 0; $dot < 24; $dot++) {
            $svg[] = sprintf(
                '<circle cx="%1$d" cy="%2$d" r="%3$d" fill="%4$s" opacity="0.35"/>',
                wp_rand(4, $width - 4),
                wp_rand(4, $height - 4),
                wp_rand(1, 2),
                $this->captcha_palette_color()
            );
        }

        $svg[] = '</svg>';

        return implode('', $svg);
    }

    private function captcha_palette_color()
    {
        $colors = array('#111827', '#1f2937', '#0f766e', '#7c3aed', '#b91c1c', '#1d4ed8');

        return $colors[wp_rand(0, count($colors) - 1)];
    }

    private function get_security_log_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'kr_forms_security_log';
    }

    private function get_request_log_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'kr_forms_request_log';
    }

    private function create_security_log_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_security_log_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            logged_at datetime NOT NULL,
            event_type varchar(80) NOT NULL,
            form_id varchar(120) NOT NULL DEFAULT '',
            form_name varchar(191) NOT NULL DEFAULT '',
            ip_address varchar(64) NOT NULL DEFAULT '',
            details text NOT NULL,
            PRIMARY KEY  (id),
            KEY logged_at (logged_at),
            KEY event_type (event_type),
            KEY form_id (form_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private function create_request_log_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $this->get_request_log_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            logged_at datetime NOT NULL,
            request_status varchar(80) NOT NULL,
            form_id varchar(120) NOT NULL DEFAULT '',
            form_name varchar(191) NOT NULL DEFAULT '',
            ip_address varchar(64) NOT NULL DEFAULT '',
            page_url text NOT NULL,
            sender_email varchar(191) NOT NULL DEFAULT '',
            summary_text text NOT NULL,
            details text NOT NULL,
            PRIMARY KEY  (id),
            KEY logged_at (logged_at),
            KEY request_status (request_status),
            KEY form_id (form_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private function insert_security_log_entry($entry)
    {
        global $wpdb;

        $wpdb->insert(
            $this->get_security_log_table_name(),
            array(
                'logged_at' => $entry['timestamp'],
                'event_type' => $entry['type'],
                'form_id' => $entry['form_id'],
                'form_name' => $entry['form_name'],
                'ip_address' => $entry['ip'],
                'details' => $entry['details'],
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    private function insert_request_log_entry($entry)
    {
        global $wpdb;

        $wpdb->insert(
            $this->get_request_log_table_name(),
            array(
                'logged_at' => $entry['timestamp'],
                'request_status' => $entry['status'],
                'form_id' => $entry['form_id'],
                'form_name' => $entry['form_name'],
                'page_url' => $entry['page_url'],
                'sender_email' => $entry['email'],
                'summary_text' => $entry['summary'],
                'details' => $entry['details'],
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    private function trim_security_log_table($limit)
    {
        global $wpdb;

        $limit = max(10, (int) $limit);
        $table_name = $this->get_security_log_table_name();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($count <= $limit) {
            return;
        }

        $offset = $limit - 1;
        $threshold_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1 OFFSET %d",
                $offset
            )
        );

        if ($threshold_id > 0) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE id < %d",
                    $threshold_id
                )
            );
        }
    }

    private function trim_request_log_table($limit)
    {
        global $wpdb;

        $limit = max(50, (int) $limit);
        $table_name = $this->get_request_log_table_name();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($count <= $limit) {
            return;
        }

        $offset = $limit - 1;
        $threshold_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1 OFFSET %d",
                $offset
            )
        );

        if ($threshold_id > 0) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE id < %d",
                    $threshold_id
                )
            );
        }
    }

    private function clear_log_table($table_name)
    {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$table_name}");
    }

    private function default_settings()
    {
        return array(
            'recipient_email' => '',
            'from_name' => get_bloginfo('name') ?: 'WordPress',
            'from_email' => get_option('admin_email') ?: '',
            'reply_to_mode' => 'sender',
            'smtp_enabled' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_auth' => true,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_timeout' => 20,
            'rate_limit_enabled' => true,
            'rate_limit_max_attempts' => 5,
            'rate_limit_window_minutes' => 10,
            'security_logging_enabled' => true,
            'security_log_limit' => 200,
            'security_alerts_enabled' => false,
            'security_alert_email' => '',
            'trusted_proxies' => '',
            'editor_preview_background' => '#f6f7f7',
        );
    }

    private function default_design_settings()
    {
        return array(
            'style_text_color' => '#0f172a',
            'style_label_color' => '#0f172a',
            'style_background_mode' => 'solid',
            'style_background_color' => '#f8fafc',
            'style_field_background_mode' => 'solid',
            'style_field_background' => '#ffffff',
            'style_border_color' => '#c7d0db',
            'style_button_background' => '#0f766e',
            'style_button_text' => '#ffffff',
            'style_button_shape' => 'round',
            'style_success_background_mode' => 'solid',
            'style_success_background' => '#dcfce7',
            'style_success_text' => '#14532d',
            'style_error_background_mode' => 'solid',
            'style_error_background' => '#fee2e2',
            'style_error_text' => '#7f1d1d',
            'style_border_radius' => 14,
        );
    }
}
