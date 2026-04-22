import React from "react";
import { __, sprintf } from "@wordpress/i18n";
import { BsThreeDotsVertical } from "react-icons/bs";
import { Menu, MenuButton, MenuItem, MenuItems, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import { outlineBtn } from "../../../assets/scss/chakra/recipe";

const ZAPMenu = ({
  triggerLabel,
  items = [],
  isIcon = false,
  variant = "outline",
  trigger
}) => {
  return (
    <Menu as="div" className="relative inline-block text-left">
      <div>
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
            <button className="px-4 py-2 border rounded-md hover:bg-gray-50">
              {__(triggerLabel, "zaplane")}
            </button>
          ))}
        </MenuButton>
      </div>
      <Transition
        as={Fragment}
        enter="transition ease-out duration-100"
        enterFrom="transform opacity-0 scale-95"
        enterTo="transform opacity-100 scale-100"
        leave="transition ease-in duration-75"
        leaveFrom="transform opacity-100 scale-100"
        leaveTo="transform opacity-0 scale-95"
      >
        <MenuItems className="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
          <div className="py-1">
            {items.map((item, index) => {
              if (item.type === "divider") {
                return <div key={index} className="my-1 border-t border-gray-200" />;
              }
              const Icon = item.icon;
              return (
                <MenuItem key={index}>
                  {({ active }) => (
                    <button
                      onClick={e => {
                        e.stopPropagation();
                        item.onClick(e);
                      }}
                      className={`${
                        active ? 'bg-gray-100 text-gray-900' : 'text-gray-700'
                      } group flex w-full items-center px-4 py-2 text-sm whitespace-nowrap`}
                    >
                      {Icon && <Icon className="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500" aria-hidden="true" />}
                      {sprintf(__("%s", "zaplane"), item.label)}
                    </button>
                  )}
                </MenuItem>
              );
            })}
          </div>
        </MenuItems>
      </Transition>
    </Menu>
  );
};
export default ZAPMenu;