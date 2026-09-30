import { expect, Page } from '@playwright/test';

export async function buyTheNextcloudPlan( page: Page, email: string ): Promise< number > {
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

	return Number( page.url().match( /order-received\/(\d+)\// )![ 1 ] );
}

export async function logInAsAdmin( page: Page ): Promise< void > {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
}

export async function openOrder( page: Page, orderId: number ): Promise< void > {
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
}
