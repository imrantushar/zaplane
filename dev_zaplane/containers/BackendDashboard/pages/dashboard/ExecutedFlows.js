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

  return (
    <div className="bg-white rounded-[8px] border border-[#E2E8F0] w-full min-h-[300px]">
      <div className="p-6">
        <span className="text-[#4A5568] text-[16px] font-[500]">
          {__('Top Executed Flows', 'zaplane')}
        </span>
      </div>
      <div className="border-t border-[#E2E8F0]" />
      <div className="p-6">
        <div className="flex flex-col gap-4">
          {!flows?.length ? (
            <span className="text-center text-[14px] text-[#718096]">
              {__('No executed flows found.', 'zaplane')}
            </span>
          ) : (
            flows?.map((f, index) => (
              <div key={index} className="flex justify-between items-center">
                <span className="text-[#2D3748] text-[15px] font-[400] truncate max-w-[250px]">
                  {f.title || __('Untitled Flow', 'zaplane')}
                </span>
                <span className="text-[#1A202C] text-[15px] font-[500]">
                  {f.total_runs || 0}
                </span>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
};
export default ExecutedFlows;
