#!/usr/bin/env node
/**
 * Print the CHANGELOG.md section for a version, as HTML, for a GitHub
 * release body.
 *
 * Usage: node bin/release-notes.js <version-or-tag> [--markdown]
 *
 * The GitHub updater copies the release body into the "View details"
 * changelog tab, and WordPress prints it there through wp_kses() without
 * converting Markdown. HTML renders in that tab and on the GitHub release
 * page alike, so the default output is HTML. --markdown prints the section
 * as written.
 *
 * A leading "v" is ignored. A prerelease tag (v2.0.0-rc.1) uses its own
 * section if there is one, otherwise the section for its base version.
 * Exits 1, printing nothing, when no section matches.
 *
 * Converts only the Markdown this changelog uses: ### headings, - lists
 * (nested by two spaces), paragraphs, **bold**, `code` and [links](url).
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const args = process.argv.slice( 2 );
const markdown = args.includes( '--markdown' );
const tag = args.find( ( arg ) => ! arg.startsWith( '--' ) );

if ( ! tag ) {
	process.stderr.write(
		'Usage: node bin/release-notes.js <version-or-tag> [--markdown]\n'
	);
	process.exit( 2 );
}

const version = tag.replace( /^refs\/tags\//, '' ).replace( /^v/, '' );
const candidates = [ version ];
if ( version.includes( '-' ) ) {
	candidates.push( version.split( '-' )[ 0 ] );
}

const changelog = fs.readFileSync(
	path.join( __dirname, '..', 'CHANGELOG.md' ),
	'utf8'
);
const lines = changelog.split( /\r?\n/ );

/**
 * Find the body lines of the "## [version]" section.
 *
 * @param {string} wanted Version without a leading "v".
 * @return {{heading: string, body: string[]}|null} Section, or null.
 */
function findSection( wanted ) {
	const start = lines.findIndex(
		( line ) =>
			line.startsWith( `## [${ wanted }]` ) &&
			/^($|\s)/.test( line.slice( `## [${ wanted }]`.length ) )
	);
	if ( -1 === start ) {
		return null;
	}
	let end = lines.findIndex(
		( line, i ) => i > start && line.startsWith( '## ' )
	);
	if ( -1 === end ) {
		end = lines.length;
	}
	const body = lines.slice( start + 1, end );
	while ( body.length && '' === body[ 0 ].trim() ) {
		body.shift();
	}
	while ( body.length && '' === body[ body.length - 1 ].trim() ) {
		body.pop();
	}
	return body.length ? { heading: lines[ start ].trim(), body } : null;
}

/**
 * Escape text for HTML.
 *
 * @param {string} text Raw text.
 * @return {string} Escaped text.
 */
function escapeHtml( text ) {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

/**
 * Convert inline Markdown (code, bold, links) in one line of text.
 *
 * Code spans are swapped out first so nothing inside them is converted.
 *
 * @param {string} text Markdown text.
 * @return {string} HTML.
 */
function inline( text ) {
	const codes = [];
	let html = text.replace( /`([^`]+)`/g, ( match, code ) => {
		codes.push( `<code>${ escapeHtml( code ) }</code>` );
		return `\u0000${ codes.length - 1 }\u0000`;
	} );
	html = escapeHtml( html )
		.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
		.replace( /\[([^\]]+)\]\(([^)\s]+)\)/g, '<a href="$2">$1</a>' );
	return html.replace(
		/\u0000(\d+)\u0000/g,
		( match, i ) => codes[ Number( i ) ]
	);
}

/**
 * Convert the section's Markdown lines to HTML.
 *
 * @param {string[]} body Section lines.
 * @return {string} HTML.
 */
function toHtml( body ) {
	const out = [];
	const lists = []; // Indent of each open <ul>.
	let paragraph = [];

	const closeParagraph = () => {
		if ( paragraph.length ) {
			out.push( `<p>${ inline( paragraph.join( ' ' ) ) }</p>` );
			paragraph = [];
		}
	};
	const closeLists = ( toIndent = -1 ) => {
		while ( lists.length && lists[ lists.length - 1 ] > toIndent ) {
			lists.pop();
			out.push( '</li></ul>' );
		}
	};

	for ( const line of body ) {
		const heading = line.match( /^(#{3,6})\s+(.*)$/ );
		const item = line.match( /^(\s*)[-*]\s+(.*)$/ );

		if ( '' === line.trim() ) {
			closeParagraph();
			closeLists();
		} else if ( heading ) {
			closeParagraph();
			closeLists();
			const level = heading[ 1 ].length;
			out.push(
				`<h${ level }>${ inline( heading[ 2 ].trim() ) }</h${ level }>`
			);
		} else if ( item ) {
			closeParagraph();
			const indent = item[ 1 ].length;
			closeLists( indent );
			if ( lists.length && lists[ lists.length - 1 ] === indent ) {
				out.push( '</li>' );
			} else {
				lists.push( indent );
				out.push( '<ul>' );
			}
			out.push( `<li>${ inline( item[ 2 ].trim() ) }` );
		} else if ( lists.length ) {
			// A wrapped list item: carry on the open <li>.
			out.push( ` ${ inline( line.trim() ) }` );
		} else {
			paragraph.push( line.trim() );
		}
	}
	closeParagraph();
	closeLists();

	return (
		out.join( '\n' ).replace( /\n(<\/li>|<\/li><\/ul>| )/g, '$1' ) + '\n'
	);
}

let section = null;
for ( const candidate of candidates ) {
	section = findSection( candidate );
	if ( section ) {
		break;
	}
}

if ( ! section ) {
	process.stderr.write(
		`No CHANGELOG.md section for ${ candidates.join( ' or ' ) }\n`
	);
	process.exit( 1 );
}

process.stderr.write(
	`Release notes from CHANGELOG.md "${ section.heading }"\n`
);

process.stdout.write(
	markdown ? section.body.join( '\n' ) + '\n' : toHtml( section.body )
);
