# Integrate WooCommerce to Nextcloud plugin admin_group_manager

Send requests from WooCommerce to endpoints of plugin [admin_group_manager](https://github.com/LibreSign/admin_group_manager/).

## Configuring product

### Adding attributes

Will be necessary add two attributes, quota and apps. Quota will be send to API as string and apps will be send as array.

To send the value of an attribute to API, the  `Name` need to follow the pattern:
```regex
^nextcloud-(?<type>string|list)-(?<name>.+)
```
And the value, if type is array, will be separated by |.

Example:

```gherkin
scenario:
    When Go to "Producs"
    And Edit the product that you want to integrate to Nextcloud
    And Click at "Attributes"
    And Click at "Add new"
    And Fill "nextcloud-string-quota" at field "Name:"
    # The size here need to be the same of other visible attribute that will be displayed to user
    And Fill "1Gb" at field "Value(s):"
    And Uncheck the field "Visible on the product page"
    And Click at "Add new"
    And Fill "nextcloud-list-apps" at field "Name:"
    And Fill "libresign|deck" at field "Value(s):"
    And Uncheck the field "Visible on the product page"
    Then Click at "Save attributes"
```

## Development

Every check is a Composer script:

```bash
composer lint  # php -l on every file
composer cs    # PHPCS
composer stan  # PHPStan
composer test  # PHPUnit
composer ci    # all of the above, in this order
```

Each tool has its own dependency tree under `vendor-bin/` (`bamarni/composer-bin-plugin`),
so PHPCS, PHPStan, PHPUnit and the linter never constrain each other nor the
WordPress, WooCommerce and Subscriptions versions the tests run against. A plain
`composer install` installs all of them and links their binaries into `vendor/bin`.

### Tests

`composer install` brings in WordPress itself (`vendor/wordpress`), the
WordPress test suite, WooCommerce and WooCommerce Subscriptions
(`vendor/test-plugins`), so the only thing the tests need from outside is a
MySQL/MariaDB server, a database they are allowed to wipe on every run and
`ext-sockets`.

| Variable | Default |
|---|---|
| `WP_TESTS_DB_NAME` | `wordpress_test` |
| `WP_TESTS_DB_USER` | `root` |
| `WP_TESTS_DB_PASSWORD` | `root` |
| `WP_TESTS_DB_HOST` | `mariadb` |
| `WP_TESTS_TABLE_PREFIX` | `wptests_` |
| `WP_CORE_DIR` | `vendor/wordpress` |

The defaults are the ones of the local SaaS stack, where both the database and
Composer already live inside the containers:

```bash
docker exec wordpress-docker-mariadb-1 \
  mariadb -uroot -proot -e 'CREATE DATABASE IF NOT EXISTS wordpress_test;'

docker exec -w /var/www/html/wp-content/plugins/woocommerce-nextcloud-admin-group-manager \
  wordpress-docker-wordpress-1 composer test
```

### End-to-end tests

End-to-end tests cover the critical WooCommerce provisioning workflows through
the browser. Currently, `tests/E2E/Includes/StatusProcessing.spec.ts` covers
checkout provisioning and the manual retry flow handled by
`includes/agm-status-processing.php`.

They need Docker, Node.js and a `composer install`, since the stack mounts
WooCommerce and Subscriptions from `vendor/test-plugins`:

```bash
npm ci
npx playwright install chromium
npm run env:start    # WordPress on :8889, Nextcloud stub on :8890
npm run test:e2e
npm run env:stop
```
