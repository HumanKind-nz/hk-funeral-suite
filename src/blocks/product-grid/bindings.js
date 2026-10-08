/**
 * Editor side of the HK block bindings sources (inc/bindings.php).
 *
 * The server registers hk-funeral-suite/price and hk-funeral-suite/field and
 * renders them on the front end. This adds getValues so a bound paragraph
 * previews the real value in the editor, for example inside a Query Loop of
 * caskets. Values are read-only: prices and fields are edited on the post
 * (or by the catalogue), never through a binding.
 *
 * Only registered meta reaches the editor through the REST API, so the
 * catalogue's internal keys are not available here either.
 *
 * Each source is added only when the server registered it (the server's
 * label comes with it), so turning the sources off in PHP turns them off
 * here too.
 */
import {
	getBlockBindingsSource,
	registerBlockBindingsSource,
} from '@wordpress/blocks';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

// Same thousands separators as PHP number_format().
function formatAmount( amount, decimals ) {
	const [ whole, fraction ] = Number( amount )
		.toFixed( decimals )
		.split( '.' );
	const grouped = whole.replace( /\B(?=(\d{3})+(?!\d))/g, ',' );

	return fraction ? `${ grouped }.${ fraction }` : grouped;
}

// Mirrors HKFuneralSuite\Bindings\get_price_value().
function priceText( raw, args = {} ) {
	const value = String( raw ?? '' ).trim();

	if ( value === '' ) {
		return null;
	}

	if ( ! Number.isNaN( Number( value ) ) ) {
		const decimals = Math.max( 0, parseInt( args.decimals ?? 0, 10 ) || 0 );
		return [
			String( args.prefix ?? '' ).trim(),
			'$' + formatAmount( value, decimals ),
			String( args.suffix ?? '' ).trim(),
		]
			.filter( Boolean )
			.join( ' ' );
	}

	if ( value.toUpperCase() === 'POA' ) {
		return args.poa ?? __( 'Price on application', 'hk-funeral-suite' );
	}

	return value;
}

function getMeta( select, context ) {
	const { postId, postType } = context;

	if ( ! postId || ! postType ) {
		return null;
	}

	return (
		select( coreStore ).getEditedEntityRecord(
			'postType',
			postType,
			postId
		)?.meta ?? null
	);
}

const register = ( source ) => {
	if ( getBlockBindingsSource( source.name ) ) {
		registerBlockBindingsSource( source );
	}
};

register( {
	name: 'hk-funeral-suite/price',
	usesContext: [ 'postId', 'postType' ],
	getValues( { select, context, bindings } ) {
		const meta = getMeta( select, context );
		const type = String( context.postType ?? '' ).replace( /^hk_fs_/, '' );
		const values = {};

		for ( const [ attribute, binding ] of Object.entries( bindings ) ) {
			const text = meta
				? priceText( meta[ `_hk_fs_${ type }_price` ], binding.args )
				: null;
			values[ attribute ] =
				text ?? __( 'Price (none set)', 'hk-funeral-suite' );
		}

		return values;
	},
	canUserEditValue: () => false,
} );

register( {
	name: 'hk-funeral-suite/field',
	usesContext: [ 'postId', 'postType' ],
	getValues( { select, context, bindings } ) {
		const meta = getMeta( select, context );
		const values = {};

		for ( const [ attribute, binding ] of Object.entries( bindings ) ) {
			const key = binding.args?.key ?? '';
			const value = meta && key ? meta[ key ] : undefined;
			values[ attribute ] =
				value !== undefined && value !== null && String( value ) !== ''
					? String( value )
					: key;
		}

		return values;
	},
	canUserEditValue: () => false,
} );
