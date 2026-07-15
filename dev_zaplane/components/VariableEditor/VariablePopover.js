import React, { useEffect, useState } from "react";
import WPPopover from "@ZAPComponents/Popaver/WPPopover";
import ZAPTab from "@ZAPComponents/Tab";
import { __ } from "@wordpress/i18n";
import { formatVariableKey, getContextVariableGroups, insertVariableIntoGroup } from "./helper";
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import { LuChevronDown } from "react-icons/lu";

export default function VariablePopover({
  isOpen,
  onClose,
  data,
  contextData,
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

  const handleClick = (item, variable, source = "app") => {
    const prefix = source === "context" ? item.prefix : item.node_id;
    const formattedValue = `{{${prefix}.${variable.key}}}`;
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

  const renderVariableAccordion = (items, source = "app") => (
    <div>
      {!items || items.length === 0 ? (
        <div className="flex justify-center items-center py-4">
          <span className="text-sm text-gray-500">
            {__("No data available yet", "zaplane")}
          </span>
        </div>
      ) : (
        items.map((item, index) => (
          <Disclosure
            key={item.node_id || item.key}
            as="div"
            defaultOpen
            className={`border border-gray-200
                ${index === 0 ? "rounded-t-md" : ""} 
                ${index === items.length - 1 ? "rounded-b-md" : ""} 
                ${index !== 0 ? "border-t-0" : ""}
              `}
          >
            {({ open }) => (
              <>
                <DisclosureButton className="flex w-full items-center justify-between bg-gray-50 px-3 py-2 text-left focus:outline-none">
                  <span className="zaplane-label flex-1 font-medium">
                    {source === "context" ? item.label : item.node_name}
                  </span>

                  {source === "app" && item.is_sample && (
                    <span className="mr-2 rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-xs text-amber-600">
                      {__("Sample Data", "zaplane")}
                    </span>
                  )}

                  {source === "app" && !item.is_sample && (
                    <span className="mr-2 rounded border border-green-200 bg-green-50 px-1.5 py-0.5 text-xs text-green-600">
                      {__("Live Data", "zaplane")}
                    </span>
                  )}

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
                          onClick={() => handleClick(item, v, source)}
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

                  {source === "app" && item.is_sample && (
                    <p className="px-4 pb-2 text-xs italic text-gray-400">
                      {__(
                        "Sample data - run a test to see real values",
                        "zaplane"
                      )}
                    </p>
                  )}
                </DisclosurePanel>
              </>
            )}
          </Disclosure>
        ))
      )}
    </div>
  );

  const contextGroups = getContextVariableGroups(contextData);

  const tabs = [
    {
      value: "apps",
      label: __("Apps", "zaplane"),
      content: renderVariableAccordion(data, "app"),
    },
    {
      value: "context",
      label: __("Context", "zaplane"),
      content: renderVariableAccordion(contextGroups, "context"),
    },
  ];

  return (
    <WPPopover
      isOpen={isOpen}
      onClose={onClose}
      title={null}
      prefix={prefix}
      focusOnMount={false}
    >
      <ZAPTab value={activeTab} tabs={tabs} onChange={setActiveTab} />
    </WPPopover>
  );
}
