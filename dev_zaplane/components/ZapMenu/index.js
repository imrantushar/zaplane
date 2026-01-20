import React from "react";
import { Menu } from "@chakra-ui/react"
import { Button, Portal } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";

const ZAPMenu = ({ triggerLabel, items = [] }) => {
  return (
    <Menu.Root>
      <Menu.Trigger asChild>
        <Button colorScheme="blue">{__(triggerLabel, "zaplane")}</Button>
      </Menu.Trigger>

      <Portal>
        <Menu.Positioner>
          <Menu.Content>
            {items.map((item, index) => (
              <Menu.Item
                key={index}
                onClick={item.onClick}
                style={{ cursor: "pointer" }}
              >
                {__(item.label, "zaplane")}
              </Menu.Item>
            ))}
          </Menu.Content>
        </Menu.Positioner>
      </Portal>
    </Menu.Root>
  );
};

export default ZAPMenu;
