import React from 'react';
import { Box, Flex, Text } from '@chakra-ui/react';
import { __ } from '@wordpress/i18n';

const propTypes = {};

export default function SubTopBar({ heading, headingSize, children }) {
	return (
		<Box
			className="zaplane-sub-top-header zaplane-page-content"
			// maxW="1072px"
			// w="100%"
			// mx="auto"
			mb="24px"
			// px="15px"
			display="flex"
			alignItems="center"
			justifyContent="space-between"
			boxSizing="border-box"
		>
			<Flex
				className="zaplane-sub-top-header__title"
				color="var(--zaplane-text-color)"
				fontSize="20px"
				fontWeight="500"
				lineHeight="30px"
				gap="10px"
				alignItems="center"
			>
				<Text as="span" fontSize={headingSize}>{heading}</Text>
			</Flex>
			{
				heading && <Flex
					className="zaplane-sub-top-header__content"
					alignItems="center"
					gap="16px"
				>
					{children}
				</Flex>
			}


		</Box>
	);
}

SubTopBar.propTypes = propTypes;
