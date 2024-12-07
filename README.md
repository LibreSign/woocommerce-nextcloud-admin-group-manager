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