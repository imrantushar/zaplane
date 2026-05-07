import React from 'react';
import PageLayout from "@ZAPComponents/PageLayout";
import { __ } from "@wordpress/i18n";
const Setting = () => {
  return <PageLayout title="Settings">
			<span className="text-[24px] font-[bold] mb-[20px]">
				{__("This feature is not ready yet", "zaplane")}
			</span>
		</PageLayout>;
};
export default Setting;