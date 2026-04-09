import React from "react";
import { HStack, Icon, IconButton, Menu, Text } from "@chakra-ui/react";
import { Button, Portal } from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { BsThreeDotsVertical } from "react-icons/bs";

const ZAPMenu = ({
  triggerLabel,
  items = [],
  isIcon = false,
  variant = "outline",
  trigger, 
}) => {
  return (
    <Menu.Root>
      <Menu.Trigger asChild>
        {trigger ? (
          trigger 
        ) : isIcon ? (
          <IconButton size="sm" variant={variant} aria-label="More options">
            <BsThreeDotsVertical />
          </IconButton>
        ) : (
          <Button variant={variant}>
            {__(triggerLabel, "zaplane")}
          </Button>
        )}
      </Menu.Trigger>

      <Portal>
        <Menu.Positioner>
          <Menu.Content>
            {items.map((item, index) => {
              if (item.type === "divider") {
                return <Menu.Separator key={index} />;
              }

              return (
                <Menu.Item
                  key={index}
                  onClick={item.onClick}
                  cursor="pointer"
                  _hover={{ bg: "var(--zaplane-gray)" }}
                >
                  <HStack justify="space-between" w="full">
                    <HStack gap={2}>
                      {item.icon && <Icon as={item.icon} boxSize={4} />}
                      <Text m="0">
                        {sprintf(__("%s", "zaplane"), item.label)}
                      </Text>
                    </HStack>

                    {item.rightIcon && <Icon as={item.rightIcon} />}
                  </HStack>
                </Menu.Item>
              );
            })}
          </Menu.Content>
        </Menu.Positioner>
      </Portal>
    </Menu.Root>
  );
};

export default ZAPMenu;