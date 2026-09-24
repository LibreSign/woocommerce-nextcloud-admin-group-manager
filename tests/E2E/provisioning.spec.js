const { test, expect } = require( '@playwright/test' );
const { nextcloud, buyTheNextcloudPlan, logInAsAdmin, openOrder } = require( './support' );

test.beforeEach( async () => {
	await nextcloud.answerWith( 200 );
	await nextcloud.forget();
} );

test( 'a paid order creates the admin group on Nextcloud and completes', async ( { page } ) => {
	const orderId = await buyTheNextcloudPlan( page, 'guest@example.org' );

	expect( await nextcloud.requests() ).toEqual( [
		{
			method: 'POST',
			path: '/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group',
			authorization: 'Basic ' + Buffer.from( 'admin:admin-password' ).toString( 'base64' ),
			body: {
				groupid: 'guest@example.org',
				email: 'guest@example.org',
				displayname: 'Ana Lima',
				quota: '1GB',
				'apps[0]': 'libresign',
				'apps[1]': 'deck',
			},
		},
	] );

	await logInAsAdmin( page );
	await openOrder( page, orderId );

	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-completed' );
	await expect( page.locator( '.order_notes' ) ).toContainText( 'Nextcloud sync completed successfully.' );
} );

test( 'an order Nextcloud refused stays processing until the admin retries the sync', async ( { page } ) => {
	await nextcloud.answerWith( 500 );

	const orderId = await buyTheNextcloudPlan( page, 'retry@example.org' );

	await logInAsAdmin( page );
	await openOrder( page, orderId );

	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-processing' );
	await expect( page.locator( '.order_notes' ) ).toContainText( 'Nextcloud sync failed: HTTP 500' );
	await expect( page.locator( '.order_notes' ) ).toContainText( 'A retry was scheduled automatically.' );

	await nextcloud.answerWith( 200 );
	await nextcloud.forget();

	const orderActions = page.getByRole( 'region', { name: 'Order actions' } );
	await orderActions.getByRole( 'combobox' ).selectOption( { label: 'Retry Nextcloud sync' } );
	await orderActions.getByRole( 'button', { name: 'Update' } ).click();

	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-completed' );
	await expect( page.locator( '.order_notes' ) ).toContainText( 'Nextcloud sync completed successfully after manual retry.' );

	const requests = await nextcloud.requests();
	expect( requests ).toHaveLength( 1 );
	expect( requests[ 0 ].body.groupid ).toBe( 'retry@example.org' );
} );
