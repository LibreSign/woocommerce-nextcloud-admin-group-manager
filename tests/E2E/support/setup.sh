#!/bin/sh
set -e

wp core install --url=http://localhost:8889 --title=E2E --admin_user=admin --admin_password=password --admin_email=admin@example.org --skip-email
wp rewrite structure '/%postname%/'
wp plugin activate woocommerce woocommerce-subscriptions woocommerce-nextcloud-admin-group-manager

wp option update woocommerce_coming_soon no
wp option update woocommerce_default_country BR:SP
wp option update woocommerce_currency BRL
wp option update nextcloud_api_host http://nextcloud
wp option update nextcloud_api_login admin
wp option update nextcloud_api_password admin-password

wp wc payment_gateway update cod --enabled=true --user=admin
if [ -z "$(wp post list --post_type=product --name=nextcloud-plan --format=ids)" ]; then
	wp wc product create --user=admin --porcelain \
		--name='Nextcloud plan' --slug=nextcloud-plan --regular_price=10 --virtual=true \
		--attributes='[{"name":"nextcloud-string-quota","options":["1GB"],"visible":false},{"name":"nextcloud-list-apps","options":["libresign","deck"],"visible":false}]'
fi
