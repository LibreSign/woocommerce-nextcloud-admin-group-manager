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
MySQL/MariaDB server and a database they are allowed to wipe on every run.

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

`tests/Integration/` mirrors the plugin files with `Test.php` appended:
`includes/agm-status-processing.php` is covered by
`tests/Integration/Includes/StatusProcessingTest.php`. Every test goes through
the hook WooCommerce fires in production, against real orders, products,
subscriptions and users.

Nothing is mocked. Outgoing HTTP is answered through the `pre_http_request`
filter (`tests/Support/FakeHttp.php`), which is WordPress' own extension point,
and any request that is not answered that way fails the test instead of
reaching the network.
