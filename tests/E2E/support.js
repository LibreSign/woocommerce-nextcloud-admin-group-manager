const { expect } = require( '@playwright/test' );

const NEXTCLOUD = 'http://localhost:8890';

const nextcloud = {
	async answerWith( status ) {
		await fetch( `${ NEXTCLOUD }/__status`, { method: 'PUT', body: String( status ) } );
	},

	async forget() {
		await fetch( `${ NEXTCLOUD }/__requests`, { method: 'DELETE' } );
	},

	async requests() {
		return ( await fetch( `${ NEXTCLOUD }/__requests` ) ).json();
	},
};

async function buyTheNextcloudPlan( page, email ) {
	await page.goto( '/product/nextcloud-plan/' );
	await page.getByRole( 'button', { name: 'Add to cart' } ).click();
	await expect( page.getByRole( 'alert' ) ).toContainText( 'has been added to your cart' );

	await page.goto( '/checkout/' );
	await page.getByLabel( 'Email address' ).fill( email );
	await page.getByLabel( 'First name' ).fill( 'Ana' );
	await page.getByLabel( 'Last name' ).fill( 'Lima' );
	await page.getByLabel( 'Street address' ).fill( 'Rua Augusta, 100' );
	await page.getByLabel( 'Town / City' ).fill( 'São Paulo' );
	await page.getByLabel( 'State / County' ).selectOption( 'SP' );
	await page.getByLabel( 'Postcode / ZIP' ).fill( '01305-000' );
	await page.getByLabel( 'Cash on delivery' ).check();
	await page.getByRole( 'button', { name: 'Place Order' } ).click();

	await expect( page ).toHaveURL( /\/checkout\/order-received\/\d+\// );

	return Number( page.url().match( /order-received\/(\d+)\// )[ 1 ] );
}

async function logInAsAdmin( page ) {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
}

async function openOrder( page, orderId ) {
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
}

module.exports = { nextcloud, buyTheNextcloudPlan, logInAsAdmin, openOrder };
