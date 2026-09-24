import { createServer } from 'node:http';

let requests = [];
let status = 200;

const read = ( request ) => new Promise( ( resolve ) => {
	let body = '';
	request.on( 'data', ( chunk ) => ( body += chunk ) );
	request.on( 'end', () => resolve( body ) );
} );

createServer( async ( request, response ) => {
	const body = await read( request );

	if ( request.url === '/__requests' ) {
		if ( request.method === 'DELETE' ) {
			requests = [];
		}
		response.setHeader( 'Content-Type', 'application/json' );
		response.end( JSON.stringify( requests ) );
		return;
	}

	if ( request.url === '/__status' ) {
		status = Number( body );
		response.end();
		return;
	}

	requests.push( {
		method: request.method,
		path: request.url,
		authorization: request.headers.authorization ?? '',
		body: Object.fromEntries( new URLSearchParams( body ) ),
	} );
	response.statusCode = status;
	response.setHeader( 'Content-Type', 'application/json' );
	response.end( JSON.stringify( { ocs: { meta: { statuscode: status }, data: [] } } ) );
} ).listen( 80 );
