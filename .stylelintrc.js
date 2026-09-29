/** @type {import('stylelint').Config} */
module.exports = {
	customSyntax: 'postcss-scss',
	extends: [ 'stylelint-config-recommended-scss' ],
	ignoreFiles: [ 'assets/build/**', 'node_modules/**', 'vendor/**' ],
	rules: {
		// Tailwind directives are expanded during the production build.
		'at-rule-no-unknown': null,
		'scss/at-rule-no-unknown': null,
		// The file is kept as a conventional entry point for future app styles.
		'no-empty-source': null,
		// Existing component styles intentionally layer selectors and Sass imports.
		// These order checks do not indicate invalid output after Sass compilation.
		'no-invalid-position-at-import-rule': null,
		'no-descending-specificity': null,
		'no-duplicate-selectors': null,
		'scss/comment-no-empty': null,
		'font-family-no-missing-generic-family-keyword': null,
	},
};
