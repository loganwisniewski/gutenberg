/**
 * External dependencies
 */
import morphdom from 'morphdom';

const load = () => {
	const link = document.querySelector( '.wp-block-query-pagination-next' );

	link.addEventListener( 'click', async ( e ) => {
		e.preventDefault();

		const {
			block: blockName,
			queryArg,
			newQueryArgValue,
			nonce,
		} = e.target.dataset;
		const blockSelector = `wp-block[type="${ blockName }"]`;

		// Find the root of the real DOM.
		const root = e.target.closest( blockSelector );

		const rawAttributes = root.attributes;
		const attributes = {};
		for ( let i = 0; i < rawAttributes.length; i++ ) {
			if ( rawAttributes[ i ].name !== 'type' ) {
				attributes[ rawAttributes[ i ].name ] =
					rawAttributes[ i ].value;
			}
		}
		// TODO: Need to include blocks' current attributes.
		// Fetch the HTML of the new block.
		const html = await fetchRenderedBlock(
			blockName,
			attributes,
			{ [ queryArg ]: newQueryArgValue },
			nonce
		);

		// Replace root with the new HTML.
		morphdom( root, html );

		// Change the browser URL.
		window.history.pushState( {}, '', e.target.href );

		// Scroll to the top to simulate page load.
		window.scrollTo( 0, 0 );

		// Rehydrate
		load();
	} );
};

window.addEventListener( 'load', load );

async function fetchRenderedBlock( blockName, attributes, queryArgs, nonce ) {
	// TODO: Auth should be done inside of this function (or ideally not at all.)
	const route = `wp/v2/block-renderer/${ blockName }`;
	const restApiBase = document.head.querySelector(
		'link[rel="https://api.w.org/"]'
	).href;
	const url = new URL( restApiBase + route );
	for ( const attr in attributes ) {
		url.searchParams.append( `attributes[${ attr }]`, attributes[ attr ] );
	}
	for ( const arg in queryArgs ) {
		url.searchParams.append( 'arg', queryArgs[ arg ] );
	}
	url.searchParams.append( 'context', 'edit' );
	url.searchParams.append( '_locale', 'user' );

	const headers = { 'X-WP-Nonce': nonce };
	const data = await window.fetch( url, { headers } );
	const { rendered } = await data.json();
	return rendered;
}
