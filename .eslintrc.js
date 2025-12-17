module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	rules: {
		'import/no-unresolved': 'off',
		camelcase: 'off',
		'@wordpress/no-global-event-listener': 'off',
	},
	globals: {
		__webpack_public_path__: true,
		jQuery: true,
		bodymovin: true,
		define: true,
		Cookies: true,
		localStorage: true,
	},
	parserOptions: {
		requireConfigFile: false,
		babelOptions: {
			presets: [ '@wordpress/babel-preset-default' ],
		},
	},
};
