import React from 'react';
import PageLayout from "@ZAPComponents/PageLayout";
import { Text } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";

const Setting = () => {
	return (
		<PageLayout title="Settings">
			<Text fontSize='24px' fontWeight='bold' mb='20px'>
				{__("This feature is not ready yet", "zaplane")}
			</Text>
		</PageLayout>
	);
};

export default Setting;
