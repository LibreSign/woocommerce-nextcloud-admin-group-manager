const STUB = 'http://localhost:8890';

export interface NextcloudRequest {
	method: string;
	path: string;
	authorization: string;
	body: Record< string, string >;
}

export const nextcloud = {
	async answerWith( status: number ): Promise< void > {
		await fetch( `${ STUB }/__status`, { method: 'PUT', body: String( status ) } );
	},

	async forget(): Promise< void > {
		await fetch( `${ STUB }/__requests`, { method: 'DELETE' } );
	},

	async requests(): Promise< NextcloudRequest[] > {
		return ( await fetch( `${ STUB }/__requests` ) ).json();
	},

	authorization(): string {
		return 'Basic ' + Buffer.from( 'admin:admin-password' ).toString( 'base64' );
	},
};
