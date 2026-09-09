# Integrate WooCommerce to Nextcloud plugin admin_group_manager

Send requests from WooCommerce to endpoints of plugin [admin_group_manager](https://github.com/LibreSign/admin_group_manager/).

## Compatibility

| Plugin Version | Nextcloud Version | admin_group_manager Version |
|---|---|---|
| 1.0.x | 32 - 35 | 0.1.x - 0.2.x |

This plugin communicates with Nextcloud via the OCS API endpoints provided by
[admin_group_manager](https://github.com/LibreSign/admin_group_manager/).
As long as the admin_group_manager app is installed and compatible with your
Nextcloud version, this WooCommerce plugin will work.

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