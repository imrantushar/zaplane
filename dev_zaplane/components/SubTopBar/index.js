import React from 'react';
import { __ } from '@wordpress/i18n';
const propTypes = {};
export default function SubTopBar({
  heading,
  headingSize,
  children
}) {
  return <div className="zaplane-sub-top-header zaplane-page-content mb-[24px] flex items-center justify-between">
			<div className="zaplane-sub-top-header__title flex text-[20px] font-[500] items-center gap-[10px]" style={{lineHeight:'30px'}}>
				<span style={headingSize ? {fontSize: headingSize} : {}}>{heading}</span>
			</div>
			{heading && <div gap="16px" className="zaplane-sub-top-header__content flex items-center">
					{children}
				</div>}


		</div>;
}
SubTopBar.propTypes = propTypes;