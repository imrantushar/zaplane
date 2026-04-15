import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Text, Button } from '@chakra-ui/react';
import { Link } from 'react-router-dom';

import { clearBtn } from '../../../assets/scss/chakra/recipe';
import { sliceString } from '@ZAPUtils/helper';

const ZAPLabel = ({
	type = "title",
	label = "",
	fontSize = "",
	fontWeight = "",
	textTransform = "",
	margin = "0",
	padding = "",
	color = "",
	bg = "",
	borderRadius = "",
	href = null,
	textAlign = "",
	borderBottomWidth = "",
	borderColor = "",
	icon,
	whiteSpace = "",
	lineClamp = "",
	truncate,
	lineHeight,
	enableSlice = false,
	sliceLength = 100,
	sliceMore = '...',
	textOverflow='',
}) => {
	const [isExpanded, setIsExpanded] = useState(false);

	const variantStyles = {
		heading: {
			fontSize: "20px",
			fontWeight: "200",
			color: "var(--zaplane-font-color)",
		},
		title: {
			fontSize: "sm",
			fontWeight: "medium",
			color: "var(--zaplane-font-color)",
		},
		subtitle: {
			fontSize: "sm",
			fontWeight: "normal",
			color: "gray.600",
		},
		miniTitle: {
			fontSize: "xs",
			fontWeight: "normal",
			color: "gray.600",
		},
		basic: {
			fontSize: "14px",
			fontWeight: "500",
			color: "var(--zaplane-font-color)",
		},
		simple: {
			fontSize: "14px",
			fontWeight: "400",
			color: "var(--zaplane-font-color)",
			lineHeight:'20px'
		},
		simpleLight: {
			fontSize: "12px",
			fontWeight: "400",
			color: "var(--zaplane-gray-color)",
		},
		bold: {
			fontSize: "20px",
			lineHeight:'30px',
			fontWeight: "500",
			color: "var(--zaplane-font-color)",
		},
		boldLight: {
			fontSize: "16px",
			fontWeight: "500",
			color: "var(--zaplane-gray-color)",
		},
		inputLabel: {
			fontSize: "14px",                 
			fontWeight: "500",              
			color: "var(--zaplane-font-color)", 
		    lineHeight:'20px',
			margin:'0'
			
		}
		
	};

	const styles = variantStyles[type] || variantStyles?.title;

	const textProps = {
		fontSize: fontSize || styles?.fontSize,
		fontWeight: fontWeight || styles?.fontWeight,
		textTransform: textTransform,
		color: color || styles?.color,
		bg: bg,
		borderRadius: borderRadius,
		margin: margin,
		padding: padding,
		fontFamily: "var(--zaplane-font)",
		textAlign: textAlign,
		borderBottomWidth: borderBottomWidth,
		borderColor: borderColor,
		whiteSpace: whiteSpace,
		truncate: truncate,
		lineClamp: lineClamp,
		lineHeight: lineHeight,
		textOverflow:textOverflow
	};


	

	return (
		<>
			{ href ? (
				<Button { ...clearBtn }>
					<Link to={ href } style={{ color: "var(--zaplane-font-color)" }}>
						<Text { ...textProps } _hover={ { color: '#4F46E5' } }>
							{ `${ label }` }
						</Text>
					</Link>
				</Button>
			) : (
				<Text { ...textProps }>{ `${ label }` }</Text>
			) }
		</>
	);
};

export default ZAPLabel;
