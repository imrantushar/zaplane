const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const isDevelopment = process.env.NODE_ENV !== 'production';
const path = require( 'path' );

const config = {
	...defaultConfig,
	entry: {
		app: path.resolve( __dirname, 'dev_zaplane/app.js' ),
	},
	output: {
		filename: '[name].js',
		path: path.resolve( __dirname, 'assets/build' ),
	},
	plugins: [ ...defaultConfig.plugins, new CleanWebpackPlugin() ],
	resolve: {
		alias: {
			...defaultConfig.resolve.alias,
			'@ZAPComponents': path.resolve( __dirname, 'src/components/' ),
			'@ZAPContainers': path.resolve( __dirname, 'src/containers/' ),
			'@ZAPPages': path.resolve( __dirname, 'src/containers/pages/' ),
			'@ZAPCustomizer': path.resolve( __dirname, 'src/customizer/' ),
			'@ZAPGlobal': path.resolve( __dirname, 'src/global/' ),
			'@ZAPRedux': path.resolve( __dirname, 'src/redux/' ),
			'@ZAPUtils': path.resolve( __dirname, 'src/utils/' ),
		},
	},
};

module.exports = config;
