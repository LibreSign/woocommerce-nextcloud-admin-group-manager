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
