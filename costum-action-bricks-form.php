<?php
/**
 * Plugin Name: Costum Action for Bricksform to Webhook Plugin
 * Description: Mengirim data formulir Bricks ke webhook berdasarkan ID formulir.
 * Version: 1.3
 * Author: bungrahman
 */

// Fungsi untuk memeriksa apakah tema Bricks atau child theme dari Bricks aktif
function is_bricks_theme_active() {
    $theme = wp_get_theme();
    if ($theme->template === 'bricks' || $theme->stylesheet === 'bricks') {
        return true;
    }
    return false;
}

// Jika tema Bricks atau child theme dari Bricks tidak aktif, tampilkan pesan dan hentikan plugin
if (!is_bricks_theme_active()) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Bricks Webhook Plugin requires the Bricks theme or a child theme of Bricks to be active.</p></div>';
    });
    return;
}

// Add settings menu
add_action('admin_menu', 'bricks_webhook_plugin_menu');

function bricks_webhook_plugin_menu() {
    add_options_page(
        'Bricks Webhook Plugin Settings',
        'Bricks Webhook',
        'manage_options',
        'bricks-webhook-plugin',
        'bricks_webhook_plugin_settings_page'
    );
}

// Display settings page
function bricks_webhook_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>Bricks Webhook Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('bricks_webhook_plugin_settings');
            do_settings_sections('bricks-webhook-plugin');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'bricks_webhook_plugin_settings_init');

function bricks_webhook_plugin_settings_init() {
    register_setting('bricks_webhook_plugin_settings', 'bricks_webhook_plugin_settings');

    add_settings_section(
        'bricks_webhook_plugin_settings_section',
        'Webhook Settings',
        'bricks_webhook_plugin_settings_section_callback',
        'bricks-webhook-plugin'
    );

    add_settings_field(
        'bricks_webhook_plugin_form_webhooks',
        'Form ID and Webhooks',
        'bricks_webhook_plugin_form_webhooks_render',
        'bricks-webhook-plugin',
        'bricks_webhook_plugin_settings_section'
    );
}

function bricks_webhook_plugin_settings_section_callback() {
    echo 'Masukkan ID formulir dan URL webhook (pisahkan dengan koma untuk beberapa webhook).';
}

function bricks_webhook_plugin_form_webhooks_render() {
    $options = get_option('bricks_webhook_plugin_settings');
    $form_webhooks = isset($options['form_webhooks']) ? $options['form_webhooks'] : '';
    ?>
    <textarea name='bricks_webhook_plugin_settings[form_webhooks]' rows='10' cols='50'><?php echo esc_textarea($form_webhooks); ?></textarea>
    <p>Format: form_id1=url1,url2;form_id2=url3,url4</p>
    <?php
}

// Hook into the form action
add_action('bricks/form/custom_action', 'pro_bricks_form_to_webhook', 10, 1);

function pro_bricks_form_to_webhook($form) {
    $data = $form->get_fields();
    $formId = $data['formId'];

    $options = get_option('bricks_webhook_plugin_settings');
    $form_webhooks = isset($options['form_webhooks']) ? $options['form_webhooks'] : '';

    // Convert settings to array
    $form_webhook_array = [];
    $entries = explode(';', $form_webhooks);
    foreach ($entries as $entry) {
        list($id, $urls) = explode('=', $entry);
        $form_webhook_array[trim($id)] = array_map('trim', explode(',', $urls));
    }

    // Cek apakah ID formulir sesuai dengan yang ada di pengaturan
    if (isset($form_webhook_array[$formId])) {
        $webhook_urls = $form_webhook_array[$formId];
        $errors = array();

        foreach ($webhook_urls as $webhook_url) {
            $curl = curl_init($webhook_url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $result = curl_exec($curl);

            if ($result === false) {
                $errors[] = 'Webhook failed for URL: ' . $webhook_url;
            } else {
                $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($response_code != 200) {
                    $errors[] = 'Webhook responded with an error for URL: ' . $webhook_url;
                }
            }

            curl_close($curl);
        }

        if (!empty($errors)) {
            $form->set_result([
                'action' => 'my_custom_action',
                'type'    => 'danger',
                'message' => esc_html__('Some webhooks failed: ' . implode(', ', $errors), 'bricks'),
            ]);
        } else {
            $form->set_result([
                'action' => 'my_custom_action',
                'type'    => 'success',
                'message' => esc_html__('All webhooks succeeded', 'bricks'),
            ]);
        }
    }
}

// Hapus opsi saat plugin dinonaktifkan
register_deactivation_hook(__FILE__, 'bricks_webhook_plugin_deactivation');

function bricks_webhook_plugin_deactivation() {
    delete_option('bricks_webhook_plugin_settings');
}
