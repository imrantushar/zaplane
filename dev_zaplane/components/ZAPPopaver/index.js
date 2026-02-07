import React from "react";
import {
  Button,
  Input,
  Popover,
  Portal,
  Text,
  VStack,
} from "@chakra-ui/react";

const ZAPPopaver = ({ triggerLabel = "Click me", title, children }) => {
  return (
    <Popover.Root>
      <Popover.Trigger asChild>
        <Button size="sm" variant="outline">
          {triggerLabel}
        </Button>
      </Popover.Trigger>
      <Portal>
        <Popover.Positioner>
          <Popover.Content >
            <Popover.Arrow />
            <Popover.Body>
              {title && (
                <Text fontWeight="medium" mb="2">
                  {title}
                </Text>
              )}
              <VStack spacing={3} align="stretch">
                {children}
              </VStack>
            </Popover.Body>
          </Popover.Content>
        </Popover.Positioner>
      </Portal>
    </Popover.Root>
  );
};

export default ZAPPopaver;
