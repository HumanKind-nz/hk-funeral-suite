/**
 * Team Member meta block registration.
 *
 * Stores data in post meta via useEntityProp, not in block content.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './editor.scss';

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
