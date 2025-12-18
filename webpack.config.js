const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const isDevelopment = process.env.NODE_ENV !== 'production';
const path = require( 'path' );

const config = {
	...defaultConfig,
	entry: {
		app: path.resolve( __dirname, 'dev_zaplane/backend.js' ),
	},
	output: {
		filename: '[name].js',
		path: path.resolve( __dirname, 'assets/build' ),
	},
	plugins: [ ...defaultConfig.plugins, new CleanWebpackPlugin() ],
	resolve: {
		alias: {
			...defaultConfig.resolve.alias,
			'@ZAPComponents': path.resolve( __dirname, 'dev_zaplane/components/' ),
			'@ZAPContainers': path.resolve( __dirname, 'dev_zaplane/containers/' ),
			'@ZAPPages': path.resolve( __dirname, 'dev_zaplane/containers/pages/' ),
			'@ZAPCustomizer': path.resolve( __dirname, 'dev_zaplane/customizer/' ),
			'@ZAPGlobal': path.resolve( __dirname, 'dev_zaplane/global/' ),
			'@ZAPRedux': path.resolve( __dirname, 'dev_zaplane/redux/' ),
			'@ZAPUtils': path.resolve( __dirname, 'dev_zaplane/utils/' ),
		},
	},
};

module.exports = config;
