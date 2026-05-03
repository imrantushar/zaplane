import { createSystem, defaultConfig } from '@chakra-ui/react';

export const theme = createSystem(defaultConfig, {
	disableLayers: false,
	disableGlobalStyle: true,
	theme: {
		tokens: {
			fonts: {
				body: { value: 'var(--zaplane-font)' },
				heading: { value: 'var(--zaplane-font)' },
			},
			colors: {
				brand: {
					primary: { value: 'var(--zaplane-primary-color)' },
					secondary: { value: 'var(--zaplane-secondary-color)' },
					gray: { value: 'var(--zaplane-gray-color)' },
					darkGray: { value: 'var(--zaplane-secondary-gray-color)' },
					border: { value: 'var(--zaplane-border-color)' },
					disabled: { value: 'var(--zaplane-text-disable)' },
					bgGray: { value: 'var(--zaplane-background-gray-color)' },
					warning: { value: 'var(--zaplane-warning-color)' },
					blue: { value: 'var(--zaplane-primary)' },
				},
			},
		},
	},
});
