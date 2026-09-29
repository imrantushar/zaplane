import React from 'react';
const propTypes = {};
export default function SubTopBar({
  heading,
  headingSize,
  children,
  inlineOnMobile = false
}) {
  const className = `zaplane-sub-top-header zaplane-page-content mb-[24px] flex flex-wrap gap-4 items-center justify-between${inlineOnMobile ? ' zaplane-sub-top-header--inline-mobile' : ''}`;
  return <div className={className}>
			<div className="zaplane-sub-top-header__title flex text-[20px] font-[500] items-center gap-[10px]" style={{lineHeight:'30px'}}>
				<span style={headingSize ? {fontSize: headingSize} : {}}>{heading}</span>
			</div>
			{heading && <div className="zaplane-sub-top-header__content flex flex-wrap gap-4 items-center">
					{children}
				</div>}


		</div>;
}
SubTopBar.propTypes = propTypes;