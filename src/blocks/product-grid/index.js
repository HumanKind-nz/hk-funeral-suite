/**
 * Product grid block registration.
 *
 * A dynamic block: the server renders it (inc/product-grid.php), the
 * editor previews it with ServerSideRender, and nothing is saved but the
 * block comment.
 *
 * Also registers the editor side of the HK block bindings sources, so
 * bound paragraphs preview real values in the editor.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './bindings';
import './style.scss';
import './editor.scss';

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
