import React from 'react';
import { __ } from '@wordpress/i18n';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import { outlineBtn } from '../../../assets/scss/chakra/recipe';
import { FiHelpCircle } from 'react-icons/fi';
import { useThemeMode } from '@ZAPUtils/theme';
import './styles.scss';

const SunIcon = () => (
	<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M12 3V4M12 20V21M4 12H3M6.31412 6.31412L5.5 5.5M17.6859 6.31412L18.5 5.5M6.31412 17.69L5.5 18.5M17.6859 17.69L18.5 18.5M21 12H20M16 12C16 14.2091 14.2091 16 12 16C9.79086 16 8 14.2091 8 12C8 9.79086 9.79086 8 12 8C14.2091 8 16 9.79086 16 12Z" stroke="var(--zaplane-font-color)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
	</svg>
);

const MoonIcon = () => (
	<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="var(--zaplane-font-color)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
	</svg>
);

const ThemeToggle = () => {
	const { isDark, toggle } = useThemeMode();
	return (
		<button
			type="button"
			onClick={toggle}
			title={isDark ? __('Switch to light mode', 'zaplane') : __('Switch to dark mode', 'zaplane')}
			aria-label={isDark ? __('Switch to light mode', 'zaplane') : __('Switch to dark mode', 'zaplane')}
			className="flex h-9 w-9 items-center justify-center rounded-full border border-[var(--zaplane-border-color)] bg-[var(--zaplane-body-background)] transition-colors"
		>
			{isDark ? <SunIcon /> : <MoonIcon />}
		</button>
	);
};

const TopBar = ({
	title = '',
	render = () => null,
	rightContent = () => <div className="flex items-center gap-3">
		<ThemeToggle />
		<button className='flex items-center gap-2' style={outlineBtn} onClick={() => {
			window.open('https://zaplane.app/docs/', '_blank');
		}}>
			<FiHelpCircle color='var(--zaplane-font-color)' />
			{__("Help")}
		</button>
	</div>,
	middleContent = () => null,
	leftContent = () => null,
	topBarStyles = {}
}) => {
	// if (!is_admin) return null;
	return <React.Fragment>
		<div style={{ position: 'sticky', top: '32px', zIndex: 999, height: '72px', width: '100%', borderBottom: '1px solid var(--zaplane-border-color)', background: 'var(--zaplane-background)', ...topBarStyles }} className="zaplane-topbar flex justify-between items-center px-6 py-4 mb-[32px]">
			<div className="flex items-center flex-wrap gap-3">
				{render()}
				{title ? <ZAPLabel label={title} fontSize="md" fontWeight="medium" /> : null}
				{leftContent()}
			</div>

			{middleContent()}

			<div className="flex items-center gap-2">
				{rightContent()}
			</div>
		</div>
	</React.Fragment>;
};
export default TopBar;
