const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: 'tests/E2E',
	workers: 1,
	forbidOnly: !! process.env.CI,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: 'http://localhost:8889',
		trace: 'retain-on-failure',
	},
} );
