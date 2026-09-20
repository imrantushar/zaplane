import React from "react";
import { __, sprintf } from "@wordpress/i18n";
import { BsThreeDotsVertical } from "react-icons/bs";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { outlineBtn } from "../../../assets/scss/chakra/recipe";

const ZAPMenu = ({
  triggerLabel,
  items = [],
  isIcon = false,
  variant = "outline",
  trigger,
  menuPlacement = "bottom"
}) => {
  const menuClassName = menuPlacement === "top"
    ? "absolute right-0 bottom-full mb-2 z-[99999] w-56 origin-bottom-right rounded-md bg-[var(--zaplane-background)] shadow-lg border border-[var(--zaplane-border-color)] focus:outline-none transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
    : "absolute right-0 mt-2 z-[99999] w-56 origin-top-right rounded-md bg-[var(--zaplane-background)] shadow-lg border border-[var(--zaplane-border-color)] focus:outline-none transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0";

  return (
    <Menu as="div" className="relative inline-block text-left">
      <MenuButton as={React.Fragment}>
        {trigger ? trigger : (isIcon ? (
          <button
            style={{...outlineBtn, padding: '8px 10px'}}
            onClick={e => e.stopPropagation()}
            aria-label="More options"
          >
            <BsThreeDotsVertical />
          </button>
        ) : (
          <button className="px-4 py-2 border rounded-md hover:bg-[var(--zaplane-secondary-color)]">
            {__(triggerLabel, "zaplane")}
          </button>
        ))}
      </MenuButton>

      <MenuItems
        transition
        className={menuClassName}
      >
        <div className="py-1">
          {items.map((item, index) => {
            if (item.type === "divider") {
              return <div key={index} className="my-1 border-t border-[var(--zaplane-border-color)]" />;
            }
            const Icon = item.icon;
            return (
              <MenuItem key={index}>
                {({ focus }) => (
                  <button
                    onClick={e => {
                      e.stopPropagation();
                      item.onClick(e);
                    }}
                    className={`${
                      focus ? 'bg-[var(--zaplane-secondary-color)] text-[var(--zaplane-font-color)]' : 'text-[var(--zaplane-font-color)]'
                    } group flex w-full items-center px-4 py-2 text-sm whitespace-nowrap`}
                  >
                    {Icon && <Icon className="mr-3 h-5 w-5 text-[var(--zaplane-text-muted)] group-hover:text-[var(--zaplane-font-secondary-color)]" aria-hidden="true" />}
                    {sprintf(__("%s", "zaplane"), item.label)}
                  </button>
                )}
              </MenuItem>
            );
          })}
        </div>
      </MenuItems>
    </Menu>
  );
};
export default ZAPMenu;
