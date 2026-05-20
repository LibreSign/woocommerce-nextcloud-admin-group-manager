<?php
defined( 'ABSPATH' ) || exit;

$a = __FILE__;
$basename = plugin_basename($a);
$plugin_domain = explode( '/', $basename )[0];
add_filter('plugin_action_links_' . $plugin_domain . '/' . $plugin_domain .'.php', 'agm_add_settings_link');

function agm_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=nextcloud-config">Configurações</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function nextcloud_config_page() {
    ?>
    <div class="wrap">
        <h1>Configurações Nextcloud</h1>
        <form method="post" action="options.php">
            <?php
            settings_errors('nextcloud_api_connection');
            settings_fields('nextcloud_options_group');
            do_settings_sections('nextcloud-admin-group-manager');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Nextcloud api host</th>
                    <td><input type="text" name="nextcloud_api_host" value="<?php echo esc_attr(get_option('nextcloud_api_host')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Nextcloud api login</th>
                    <td><input type="text" name="nextcloud_api_login" value="<?php echo esc_attr(get_option('nextcloud_api_login')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Nextcloud api password</th>
                    <td><input type="password" name="nextcloud_api_password" value="<?php echo esc_attr(get_option('nextcloud_api_password')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function nexcloud_admin_group_manager_menu() {
    add_options_page(
        'Configurações Nextcloud',
        'Nextcloud Config',
        'manage_options',
        'nextcloud-config',
        'nextcloud_config_page'
    );
}

add_action('admin_menu', 'nexcloud_admin_group_manager_menu');

function agm_admin_settings() {
    register_setting('nextcloud_options_group', 'nextcloud_api_host');
    register_setting('nextcloud_options_group', 'nextcloud_api_login');
    register_setting('nextcloud_options_group', 'nextcloud_api_password');
}

add_action('admin_init', 'agm_admin_settings');

function agm_build_nextcloud_ocs_url(string $path): string {
    $host = (string) get_option('nextcloud_api_host');
    return rtrim($host, '/') . '/' . ltrim($path, '/');
}

function agm_test_nextcloud_connection(): array {
    $host = trim((string) get_option('nextcloud_api_host'));
    $login = trim((string) get_option('nextcloud_api_login'));
    $password = (string) get_option('nextcloud_api_password');

    if ($host === '' || $login === '' || $password === '') {
        return [
            'type' => 'error',
            'message' => 'Preencha host, login e senha para testar a conexão com o Nextcloud.',
        ];
    }

    $response = wp_remote_get(
        agm_build_nextcloud_ocs_url('/ocs/v2.php/cloud/user?format=json'),
        [
            'headers' => agm_nextcloud_request_headers(),
            'timeout' => 15,
        ]
    );

    if (is_wp_error($response)) {
        return [
            'type' => 'error',
            'message' => 'Falha ao conectar no Nextcloud: ' . $response->get_error_message(),
        ];
    }

    $status_code = (int) ($response['response']['code'] ?? 0);
    $body = json_decode((string) ($response['body'] ?? ''), true);
    $ocs_status_code = (int) ($body['ocs']['meta']['statuscode'] ?? 0);
    $user_id = (string) ($body['ocs']['data']['id'] ?? '');

    if ($status_code !== 200) {
        return [
            'type' => 'error',
            'message' => 'Nextcloud respondeu com HTTP ' . $status_code . '. Verifique host e credenciais.',
        ];
    }

    if ($ocs_status_code !== 100) {
        return [
            'type' => 'error',
            'message' => 'A API OCS respondeu com status inesperado (' . $ocs_status_code . '). Verifique credenciais e permissões do usuário informado.',
        ];
    }

    return [
        'type' => 'success',
        'message' => 'Conexão com o Nextcloud validada com sucesso' . ($user_id !== '' ? ' usando o usuário ' . $user_id : '') . '.',
    ];
}

function agm_maybe_test_nextcloud_connection_after_save() {
    if (!is_admin()) {
        return;
    }

    if (!isset($_GET['page'], $_GET['settings-updated'])) {
        return;
    }

    if ($_GET['page'] !== 'nextcloud-config' || $_GET['settings-updated'] !== 'true') {
        return;
    }

    $result = agm_test_nextcloud_connection();
    add_settings_error(
        'nextcloud_api_connection',
        'nextcloud_api_connection_result',
        $result['message'],
        $result['type']
    );
}

add_action('admin_init', 'agm_maybe_test_nextcloud_connection_after_save', 20);
