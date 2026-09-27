import React from 'react';
import { __ } from '@wordpress/i18n';
import { useSelector } from 'react-redux';
const ExecutedFlows = () => {
  const {
    topExecutedFlows: flows
  } = useSelector(state => state.dashboard);

  // fetch top executed flows on mount

  return (
    <div className="bg-[var(--zaplane-background)] rounded-[8px] border border-[var(--zaplane-border-color)] w-full h-full min-h-[400px] flex flex-col">
      <div className="p-6">
        <span className="text-[var(--zaplane-font-secondary-color)] text-[16px] font-[500]">
          {__('Top Executed Flows', 'zaplane')}
        </span>
      </div>
      <div className="border-t border-[var(--zaplane-border-color)]" />
      <div className="p-6 flex-1">
        <div className="flex flex-col gap-4">
          {!flows?.length ? (
            <span className="text-center text-[14px] text-[var(--zaplane-text-muted)]">
              {__('No executed flows found.', 'zaplane')}
            </span>
          ) : (
            flows?.map((f, index) => {
              const title = f.title || __('Untitled Flow', 'zaplane');

              return (
                <div key={index} className="flex items-center gap-3">
                  <span
                    className="min-w-0 flex-1 truncate text-[15px] font-[400] text-[var(--zaplane-font-color)]"
                    title={title}
                  >
                    {title}
                  </span>
                  <span className="shrink-0 text-[15px] font-[500] text-[var(--zaplane-font-color)]">
                    {f.total_runs || 0}
                  </span>
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
};
export default ExecutedFlows;
