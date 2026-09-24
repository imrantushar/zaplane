module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	rules: {
		// This project predates the current WordPress formatter and deliberately
		// uses a mixed formatting style. Keep lint focused on code correctness;
		// formatting is handled independently by the editor and npm run format.
		'prettier/prettier': 'off',
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
