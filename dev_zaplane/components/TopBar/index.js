import React from 'react';
import PropTypes from 'prop-types';
import { Button, Flex, Text } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';

import './styles.scss';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
import { outlineBtn } from '../../../assets/scss/chakra/recipe';
import { FiHelpCircle } from 'react-icons/fi';
import WhatsNew from '@ZAPComponents/WhatsNew/WhatsNew';


const TopBar = ({
	title = '',
	render = () => null,
	rightContent = () => <Flex gap={3} alignItems="center">
		<WhatsNew />
		<Button
			{...outlineBtn}
			onClick={() => {
				window.open('https://zaplane.com/', '_blank');
			}}
		>
			<FiHelpCircle color='var(--zaplane-font-color)' />
			{__("Help")}
		</Button>
	</Flex>,
	middleContent = () => null,
	leftContent = () => null,
	topBarStyles = {},
}) => {
	const [isDark, setIsDark] = React.useState(false);

	// if (!is_admin) return null;
	return (
		<React.Fragment>
			<Flex
				className='zaplane-topbar'
				style={topBarStyles}
				direction={{ base: 'column', md: 'row' }}
				justifyContent="space-between"
				align={{ base: 'flex-start', md: 'center' }}
				height='72px'
				px={6}
				py={4}
				top="32px"
				mb="32px"
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
					{/* <Button
						bg="transparent"
						borderWidth="1px"
						borderColor="var(--zaplane-border-color)"
						background="var(--zaplane-body-background)"
						padding="5px"
						title={isDark ? __('Switch to Light Mode', 'zaplane') : __('Switch to Dark Mode', 'zaplane')}
					>
						{isDark ? (
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 3V4M12 20V21M4 12H3M6.31412 6.31412L5.5 5.5M17.6859 6.31412L18.5 5.5M6.31412 17.69L5.5 18.5M17.6859 17.69L18.5 18.5M21 12H20M16 12C16 14.2091 14.2091 16 12 16C9.79086 16 8 14.2091 8 12C8 9.79086 9.79086 8 12 8C14.2091 8 16 9.79086 16 12Z" stroke="var(--zaplane-font-color)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
							</svg>
						) : (
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="var(--zaplane-font-color)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
							</svg>
						)}
					</Button> */}
				</Flex>
			</Flex>
		</React.Fragment>
	);
};


export default TopBar;
