/**
 **** WARNING: No ES6 modules here. Not transpiled! ****
 */
/* eslint-disable import/no-nodejs-modules */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const getBaseWebpackConfig = require( '@automattic/calypso-build/webpack.config.js' );
const path = require( 'path' );

/**
 * Internal dependencies
 */
// const { workerCount } = require( './webpack.common' ); // todo: shard...

/**
 * Internal variables
 */
const index = path.join( __dirname, 'src' );

const webpackConfig = getBaseWebpackConfig(
	{ WP: true },
	{
		entry: {
			editor: index
		},
		'output-path': path.join( __dirname, 'dist' ),
	}
);

module.exports = webpackConfig;
