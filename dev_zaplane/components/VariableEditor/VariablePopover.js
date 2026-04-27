import React, { useEffect, useState } from "react";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import ZAPTab from "@ZAPComponents/Tab";
import { __ } from "@wordpress/i18n";
import { formatVariableKey, insertVariableIntoGroup } from "./helper";
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import { LuChevronDown } from "react-icons/lu";

export default function VariablePopover({
  isOpen,
  onClose,
  data,
  onSelectVariable,
  activeInput,
  groups,
  groupHelpers,
  setPopoverOpen,
  setActiveInput,
  prefix,
}) {
  const [activeTab, setActiveTab] = useState("apps");

  useEffect(() => {
    if (isOpen) {
      setActiveTab("apps");
    }
  }, [isOpen]);

  const handleClick = (item, variable) => {
    const formattedValue = `{{${item.node_id}.${variable.key}}}`;
    if (onSelectVariable) {
      onSelectVariable(formattedValue);
    } else if (activeInput && groups && groupHelpers) {
      insertVariableIntoGroup({
        activeInput,
        groups,
        groupHelpers,
        valueToInsert: formattedValue,
        setPopoverOpen,
        setActiveInput,
      });
    }
  };

  const appsContent = (
    <div>
      {!data || data.length === 0 ? (
        <div className="flex justify-center items-center py-4">
          <span className="text-sm text-gray-500">
            {__("No data available yet", "zaplane")}
          </span>
        </div>
      ) : (
        data.map((item, index) => (
          <Disclosure
            key={item.node_id}
            as="div"
            className={`border border-gray-200 
                ${index === 0 ? "rounded-t-md" : ""} 
                ${index === data.length - 1 ? "rounded-b-md" : ""} 
                ${index !== 0 ? "border-t-0" : ""}
              `}
          >
            {({ open }) => (
              <>
                <DisclosureButton className="flex w-full items-center justify-between bg-gray-50 px-3 py-2 text-left focus:outline-none">
                  <span className="zaplane-label flex-1 font-medium">
                    {item.node_name}
                  </span>

                  <LuChevronDown
                    className={`h-5 w-5 text-gray-500 transition-transform duration-200 ${
                      open ? "rotate-180" : ""
                    }`}
                  />
                </DisclosureButton>

                <DisclosurePanel className="bg-white max-h-[200px] overflow-y-auto">
                  <div className="py-2">
                    {item.variables?.length > 0 ? (
                      item.variables.map((v, vi) => (
                        <div
                          key={vi}
                          onClick={() => handleClick(item, v)}
                          className="flex items-center px-4 py-2 cursor-pointer hover:bg-gray-50 text-sm"
                        >
                          <span className="zaplane-label font-medium mr-1">
                            {__(formatVariableKey(v.key), "zaplane")}
                          </span>

                          <span className="text-gray-500 truncate whitespace-nowrap overflow-hidden font-normal">
                            {" : "}
                            {__(v.sample, "zaplane")}
                          </span>
                        </div>
                      ))
                    ) : (
                      <div className="text-center text-gray-500 text-sm py-2">
                        {__("No fields available", "zaplane")}
                      </div>
                    )}
                  </div>
                </DisclosurePanel>
              </>
            )}
          </Disclosure>
        ))
      )}
    </div>
  );

  const tabs = [
    {
      value: "apps",
      label: __("Apps", "zaplane"),
      content: appsContent,
    },
    {
      value: "context",
      label: __("Context", "zaplane"),
      content: <div className="min-h-[40px]" />,
    },
  ];

  return (
    <WPPopover
      isOpen={isOpen}
      onClose={onClose}
      title={null}
      prefix={prefix}
    >
      <ZAPTab value={activeTab} tabs={tabs} onChange={setActiveTab} />
    </WPPopover>
  );
}
