import React from 'react';
import { Button, Flex, Box, Span, Text, IconButton } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
// import { isPlainPermalink } from '@Utils/helper';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import { useDispatch, useSelector } from 'react-redux';
import './styles.scss';
import { GoMoon } from "react-icons/go";
import { IoSunnyOutline } from "react-icons/io5";

const TopBar = ({
	title = '',
	render = () => null,
	rightContent = () => null,
	middleContent = () => null,
	leftContent = () => null,
	topBarStyles = {},
}) => {
	// const { baseSettings } = useSelector((state) => state.settings);
	// const { color_theme_mode } = useSelector((state) => state.localized_data);
	// const dispatch = useDispatch();
	// const isDark = color_theme_mode === 'dark';

	// const inlineStyles =
	// 	Object.keys(baseSettings).length > 0
	// 		? `
	// 	:root {
	// 		--ecm-border-color: ${isDark ? baseSettings?.dark_border_color : baseSettings?.border_color};
	// 		--ecm-body-background: ${isDark ? baseSettings?.dark_body_color : baseSettings?.body_color};
	// 		--ecm-background: ${isDark ? baseSettings?.dark_background_color : baseSettings?.background_color};
	// 		--ecm-primary: ${isDark ? baseSettings?.dark_primary_color : baseSettings?.primary_color};
	// 		--ecm-font-color: ${isDark ? baseSettings?.dark_text_color : baseSettings?.text_color};
	// 		--ecm-secondary: ${isDark ? baseSettings?.dark_secondary_color : baseSettings?.secondary_color};
	// 	}
	// `
	// 		: '';

	// const toggleTheme = () => {
	// 	const newTheme = isDark ? 'light' : 'dark';
	// 	dispatch(updateColorLocalize(newTheme));
	// 	dispatch(
	// 		updateBaseSettings({
	// 			...baseSettings,
	// 			color_theme_mode: newTheme,
	// 			isShowNotification: false,
	// 		})
	// 	);
	// };

	const isDark = false;
	return (
		<React.Fragment>
			{/* <style>{inlineStyles}</style> */}
			<Flex
				style={topBarStyles}
				direction={{ base: 'column', md: 'row' }}
				justifyContent="space-between"
				align={{ base: 'flex-start', md: 'center' }}
				height='80px'
				px={6}
				py={4}
				mb={6}
				top="32px"
				borderBottomWidth="1px"
				borderColor="var(--zaplane-border-color)"
				boxShadowColor={'var(--zaplane-shadow)'}
				width="100%"
				bg="var(--zaplane-background)"
				position="sticky"
				zIndex={999}
			>
				<Flex
					alignItems="center"
					gap={3}
					flexWrap="wrap"
					width="fit-content"
				>
					{render()}
					{title ? (
						<ZAPLabel
							label={title}
							fontSize="md"
							fontWeight="medium"
						/>
					) : null}
					{leftContent()}
				</Flex>

				{middleContent()}

				<Flex align="center" gap={2}>
					{rightContent()}

					{isDark ? (
						<Button
							as={IconButton}
							bg="transparent"
							borderWidth="1px"
							borderColor="var(--zaplane-border-color)"
							padding="5px"
							color="var(--zaplane-font-color)"
						// onClick={toggleTheme}
						>
							<IoSunnyOutline />
						</Button>
					) : (
						<Button
							as={IconButton}
							bg="transparent"
							borderWidth="1px"
							borderColor="var(--zaplane-border-color)"
							padding="5px"
							color="var(--zaplane-font-color)"
						// onClick={toggleTheme}
						>
							<GoMoon />
						</Button>
					)}
				</Flex>
			</Flex>
		</React.Fragment>
	);
};

export default TopBar;
