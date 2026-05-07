/** @type {import('tailwindcss').Config} */
module.exports = {
	// corePlugins: {
	// 	preflight: false,
	// },
	content: [
		'./dev_zaplane/**/*.{js,jsx,ts,tsx}',
		'./assets/scss/**/*.scss',
	],
	important: '.zaplane-scope',
	theme: {
		extend: {
			colors: {
				'zaplane-border': 'var(--zaplane-border-color)',
			},
		},
	},
	safelist: [
		// ZAPAlert: status-based classes resolved at runtime from alertStyles object
		'bg-blue-50', 'border-blue-300', 'text-blue-800',
		'bg-green-50', 'border-green-300', 'text-green-800',
		'bg-yellow-50', 'border-yellow-400', 'text-yellow-800',
		'bg-red-50', 'border-red-300', 'text-red-800',
	],
	plugins: [],
};