import React from "react";
import { Menu } from "@chakra-ui/react"
import { Button, Portal } from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";

const ZAPMenu = ({ triggerLabel, items = [] }) => {
  return (
    <Menu.Root>
      <Menu.Trigger asChild>
        <Button {...primaryBtn}>{__(triggerLabel, "zaplane")}</Button>
      </Menu.Trigger>

      <Portal>
        <Menu.Positioner>
          <Menu.Content>
            {items.map((item, index) => (
              <Menu.Item
                key={index}
                onClick={item.onClick}
                cursor="pointer"
              >
                {sprintf(__('%s', 'zaplane'), item.label)}
              </Menu.Item>
            ))}
          </Menu.Content>
        </Menu.Positioner>
      </Portal>
    </Menu.Root>
  );
};

export default ZAPMenu;
