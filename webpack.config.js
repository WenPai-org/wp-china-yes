const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/Admin/app/index.js',
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve && defaultConfig.resolve.alias ),
			'use-memo-one': path.resolve(
				__dirname,
				'node_modules/@wordpress/compose/node_modules/use-memo-one'
			),
		},
	},
};
