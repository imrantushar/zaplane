import { __, sprintf } from "@wordpress/i18n";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import ZAPLoading from "@ZAPComponents/Loading";
import ReactJson from "react-json-view";
import { useSelector } from "react-redux";
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/react';
import { LuChevronDown } from 'react-icons/lu';

const LogDetails = ({
  runId,
  onBack
}) => {
  const {
    nodeDetails = [],
    isloading
  } = useSelector(state => state.workflows);
  if (isloading) {
    return <ZAPLoading />;
  }
  //after the  response I’ll add translation support.
  return (
    <div>
      <ZAPLabel label={sprintf(__('Run ID: %s', 'zaplane'), runId)} type={"inputLabel"} />
      <div className="space-y-4">
        {nodeDetails?.nodes?.map(log => {
          const input = log?.input_json || {};
          const output = log?.output_json || {};
          return (
            <Disclosure key={log.id} as="div" className="border border-[var(--zaplane-border-color)] p-3 rounded-lg my-2">
              {({ open }) => (
                <>
                  <DisclosureButton className="flex w-full justify-between items-center bg-[var(--zaplane-background)] px-2  text-left text-sm font-medium focus:outline-none">
                    <div className="flex flex-col flex-1">
                      <span className="zaplane-label font-medium">
                        {sprintf(__('%s', 'zaplane'), log?.node?.app)}
                      </span>
                      <span className="text-[var(--zaplane-font-secondary-color)]">
                        {sprintf(__('%s', 'zaplane'), log?.node?.event)}
                      </span>
                    </div>

                    <div className="flex items-center gap-4">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${
                        log.status === "completed" ? "bg-green-100 text-green-800" : 
                        log.status === "failed" ? "bg-red-100 text-red-800" : "bg-blue-100 text-blue-800"
                      }`}>
                        {__(log.status, 'zaplane')}
                      </span>
                      <LuChevronDown
                        className={`${open ? 'rotate-180 transform' : ''} h-5 w-5 text-[var(--zaplane-font-secondary-color)]`}
                      />
                    </div>
                  </DisclosureButton>
                  <DisclosurePanel className="px-2 pt-4 pb-2 text-sm text-[var(--zaplane-font-secondary-color)]">
                    <div className="flex flex-col gap-4 items-stretch">
                      <div className="p-3 border border-[var(--zaplane-border-color)] rounded-md bg-[var(--zaplane-secondary-color)]" style={{ overflowWrap: 'break-word', wordBreak: 'break-all', overflowX: 'auto' }}>
                        <span className="zaplane-label font-bold mb-2 block">
                          {__('Input', 'zaplane')}
                        </span>
                        <ReactJson src={input} name="root" collapsed={1} enableClipboard={false} displayDataTypes={false} />
                      </div>

                      <div className="p-3 border border-[var(--zaplane-border-color)] rounded-md bg-[var(--zaplane-secondary-color)]" style={{ overflowWrap: 'break-word', wordBreak: 'break-all', overflowX: 'auto' }}>
                        <span className="font-bold mb-2 block text-[var(--zaplane-font-color)]">
                          {__('Output', 'zaplane')}
                        </span>
                        <ReactJson src={output} name="root" collapsed={1} enableClipboard={false} displayDataTypes={false} />
                      </div>
                    </div>
                  </DisclosurePanel>
                </>
              )}
            </Disclosure>
          );
        })}
      </div>
    </div>
  );
};
export default LogDetails;