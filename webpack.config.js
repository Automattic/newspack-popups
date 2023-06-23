/**
 **** WARNING: No ES6 modules here. Not transpiled! ****
 */
/* eslint-disable import/no-nodejs-modules */
/* eslint-disable @typescript-eslint/no-var-requires */

/**
 * External dependencies
 */
const getBaseWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );
const path = require( 'path' );

/**
 * Internal variables
 */
const editor = path.join( __dirname, 'src', 'editor' );
const view = path.join( __dirname, 'src', 'view' );
const admin = path.join( __dirname, 'src', 'view', 'admin' );
const documentSettings = path.join( __dirname, 'src', 'document-settings' );
const settings = path.join( __dirname, 'src', 'settings' );
const blocks = path.join( __dirname, 'src', 'blocks' );
const customizer = path.join( __dirname, 'src', 'customizer' );
const criteria = path.join( __dirname, 'src', 'criteria' );

const webpackConfig = getBaseWebpackConfig(
	{ WP: true },
	{
		entry: {
			editor,
			view,
			admin,
			documentSettings,
			settings,
			blocks,
			customizer,
			criteria,
		},
		'output-path': path.join( __dirname, 'dist' ),
	}
);

module.exports = webpackConfig;
