import React from 'react';
import { __ } from '@wordpress/i18n';
import ZAPDivider from '@ZAPComponents/ZAPDivider';
import { useSelector } from 'react-redux';
import ZAPLabel from '@ZAPComponents/Labels/ZAPLabel';
const ExecutedFlows = () => {
  const {
    topExecutedFlows: flows
  } = useSelector(state => state.dashboard);

  // fetch top executed flows on mount

  return <div boxShadow="md"
  // p={"24px"}
  className="bg-var(--zaplane-background) rounded-[4px] w-[full]">
      <span className="zaplane-label text-[14px] p-[24px]">
        {__('Top Executed Flows', 'zaplane')}

      </span>
      <ZAPDivider />
      <div className="gap-2">
        <div flexDirection='column' gap='6px' className="flex p-[24px]">
          {!flows.length ? <span className="zaplane-label text-center text-[12px] text-var(--zaplane-text-secondary) ">{__('No executed flows found.', 'zaplane')}</span> : flows?.map(f => <>
              <div className="flex justify-between">
                <ZAPLabel label={f.title} type='simple' />
                <ZAPLabel label={f.total_runs} type='simple' />
                
              </div>
              </>)}

        </div>
      </div>
    </div>;
};
export default ExecutedFlows;