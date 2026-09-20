const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const webpack = require( 'webpack' );
const isDevelopment = process.env.NODE_ENV !== 'production';
const path = require( 'path' );

/**
 * A comment at the top of every generated file naming its source.
 *
 * The minifier removes ordinary comments, so this is added after it has run
 * (PROCESS_ASSETS_STAGE_ANALYSE). It is limited to .js and .css: a comment
 * ahead of the <?php in app.asset.php would be printed to the page. Skipped in
 * development builds so source maps still line up.
 */
const sourceHeader = new webpack.BannerPlugin( {
	banner: ( { filename } ) =>
		[
			'Zaplane - generated file, do not edit.',
			'',
			/\.css$/.test( filename )
				? 'Built from the Sass that dev_zaplane/app.js imports: assets/scss/backend.scss and the .scss files beside the components in dev_zaplane/.'
				: 'Built from dev_zaplane/app.js and the modules it imports from dev_zaplane/.',
			'The complete uncompiled source ships inside this plugin: dev_zaplane/ is the',
			'React source and assets/scss/ the Sass; package.json, webpack.config.js,',
			'tailwind.config.js and postcss.config.js are the build setup.',
			'',
			'To rebuild, run in the plugin directory:',
			'  npm install --legacy-peer-deps',
			'  npm run build',
			'',
			'License: GPL-3.0-or-later',
		].join( '\n' ),
	test: /\.(js|css)$/,
	entryOnly: true,
	stage: webpack.Compilation.PROCESS_ASSETS_STAGE_ANALYSE,
} );

const config = {
	...defaultConfig,
	entry: {
		app: path.resolve( __dirname, 'dev_zaplane/app.js' ),
	},
	output: {
		filename: '[name].js',
		path: path.resolve( __dirname, 'assets/build' ),
	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			{
				// The email editor's default images come from a remote host; point
				// them at copies this plugin ships instead.
				test: /[\\/]node_modules[\\/]@kodezen[\\/]editor[\\/].*\.js$/,
				use: [ path.resolve( __dirname, 'dev_zaplane/webpack/editor-placeholders.js' ) ],
			},
		],
	},
	plugins: [
		...defaultConfig.plugins,
		new CleanWebpackPlugin(),
		...( isDevelopment ? [] : [ sourceHeader ] ),
	],
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
			'@ZAPHooks': path.resolve( __dirname, 'dev_zaplane/hooks/' ),
		},
	},
};

module.exports = config;
