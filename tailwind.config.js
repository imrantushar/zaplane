/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
		'./dev_zaplane/**/*.{js,jsx,ts,tsx}',
	],
	important: '#zaplane-app',
	theme: {
		extend: {},
	},
	plugins: [],
	corePlugins: {
		preflight: false,
	},
};