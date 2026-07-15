import { useEffect, useState } from 'react';
import { settings } from '@ZAPUtils/helper';

// Per-browser preference for the active theme mode. The color values themselves
// live server-side (injected as scoped CSS variables); this only tracks which
// palette — light or dark — is active.
export const THEME_STORAGE_KEY = 'zaplane-theme-mode';

export const getDefaultMode = () =>
	settings?.theme?.default_mode === 'dark' ? 'dark' : 'light';

export const getStoredMode = () => {
	try {
		const v = window.localStorage.getItem(THEME_STORAGE_KEY);
		if (v === 'light' || v === 'dark') return v;
	} catch (e) {
		// localStorage may be unavailable; fall through to the configured default.
	}
	return getDefaultMode();
};

export const getThemeRoot = () =>
	document.querySelector('.zaplane-scope') || document.getElementById('zaplane-app');

export const getActiveMode = () => {
	const attr = document.documentElement.getAttribute('data-theme');
	if (attr === 'dark' || attr === 'light') return attr;
	return getStoredMode();
};

// Apply a mode: stamp data-theme on <html> (so it cascades to portaled UI too)
// and the app root, persist it, and notify live listeners (the topbar switch).
export const applyThemeMode = (mode) => {
	const normalized = mode === 'dark' ? 'dark' : 'light';
	document.documentElement.setAttribute('data-theme', normalized);
	const root = getThemeRoot();
	if (root) root.setAttribute('data-theme', normalized);
	try {
		window.localStorage.setItem(THEME_STORAGE_KEY, normalized);
	} catch (e) {
		// Ignore write failures (private mode, etc.); the attribute still applies.
	}
	window.dispatchEvent(new CustomEvent('zaplane-theme-change', { detail: normalized }));
	return normalized;
};

export const toggleThemeMode = () =>
	applyThemeMode(getActiveMode() === 'dark' ? 'light' : 'dark');

// Called once on boot (before React paints) to apply the stored/default mode.
export const initThemeMode = () => applyThemeMode(getStoredMode());

// A client-side <style> that mirrors the server-injected theme blocks. Used by
// the Settings page for live preview and to reflect a save without a reload —
// it's appended after the server style so it wins, and covers both modes so the
// topbar switch keeps working.
export const THEME_STYLE_ID = 'zaplane-theme-override';

export const buildThemeCss = (theme) => {
	const block = (palette) =>
		Object.entries(palette || {})
			.map(([k, v]) => `${k}:${v};`)
			.join('');
	const light = block(theme?.light);
	return `html[data-theme="light"]{${light}}html[data-theme="dark"]{${block(theme?.dark)}}.zaplane-force-light{${light}}`;
};

export const applyThemePalettes = (theme) => {
	if (!theme) return;
	let el = document.getElementById(THEME_STYLE_ID);
	if (!el) {
		el = document.createElement('style');
		el.id = THEME_STYLE_ID;
		document.head.appendChild(el);
	}
	el.textContent = buildThemeCss(theme);
};

// React binding for components that need to read/flip the mode.
export const useThemeMode = () => {
	const [mode, setMode] = useState(() => getActiveMode());
	useEffect(() => {
		const handler = (e) => setMode(e.detail === 'dark' ? 'dark' : 'light');
		window.addEventListener('zaplane-theme-change', handler);
		return () => window.removeEventListener('zaplane-theme-change', handler);
	}, []);
	return {
		mode,
		isDark: mode === 'dark',
		toggle: () => setMode(toggleThemeMode()),
		set: (m) => setMode(applyThemeMode(m)),
	};
};
