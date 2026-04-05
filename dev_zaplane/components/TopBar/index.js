import React from 'react';
import PropTypes from 'prop-types';
import { Button, Flex, Text } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';
// import { isPlainPermalink } from '@Utils/helper';
import './styles.scss';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';


const TopBar = ({
	title = '',
	render = () => null,
	rightContent = () => null,
	middleContent = () => null,
	leftContent = () => null,
	topBarStyles = {},
}) => {
	// if (!is_admin) return null;
	return (
		<React.Fragment>
			<Flex
			   className='zaplane-topbar'
				style={topBarStyles}
				direction={{ base: 'column', md: 'row' }}
				justifyContent="space-between"
				align={{ base: 'flex-start', md: 'center' }}
				height='80px'
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
					{ /* <Button
					bg="transparent"
					borderWidth="1px"
					borderColor="var(--zaplane-border-color)"
					padding="5px"
					onClick={toggleTheme}
				>
					<span
						className={`easy-content-manager-icon easy-content-manager-icon--${isDark ? 'moon' : 'sun'}`}
					/>
				</Button> */ }
				</Flex>
			</Flex>
		</React.Fragment>
	);
};


export default TopBar;
