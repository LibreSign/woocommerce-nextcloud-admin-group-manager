# Integrate WooCommerce to Nextcloud plugin admin_group_manager

Send requests from WooCommerce to endpoints of plugin [admin_group_manager](https://github.com/LibreSign/admin_group_manager/).

## Configure the follow constants at wp-config.php

```php
define( 'NEXTCLOUD_API_HOST', 'http://localhost');
define( 'NEXTCLOUD_API_LOGIN', 'admin');
define( 'NEXTCLOUD_API_PASSWORD', 'cLkw5-4MFJD-3zPEN-F5pZ3-pzQzg');
```

## Configuring product

### Adding attributes

Will be necessary add two attributes, quota and apps. Quota will be send to API as string and apps will be send as array.

Example:

```gherkin
scenario:
    When Go to "Producs"
    And Edit the product that you want to integrate to Nextcloud
    And Click at "Attributes"
    And Click at "Add new"
    And Fill "nexrtcloud-quota" at field "Name:"
    # The size here need to be the same of other visible attribute that will be displayed to user
    And Fill "1Gb" at field "Value(s):"
    And Uncheck the field "Visible on the product page"
    And Fill "nexrtcloud-apps" at field "Name:"
    And Fill "libresign|deck" at field "Value(s):"
    And Uncheck the field "Visible on the product page"
    Then Click at "Save attributes"
```