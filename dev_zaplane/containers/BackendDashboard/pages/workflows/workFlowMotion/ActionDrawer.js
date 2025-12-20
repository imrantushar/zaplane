import React from "react";
import {
  Button,
  CloseButton,
  Drawer,
  Portal,
  Text,
} from "@chakra-ui/react";

const ActionDrawer = ({ open, onClose, node }) => {
  return (
    <Drawer.Root open={open} onOpenChange={(e) => !e.open && onClose()}>
      <Portal>
        <Drawer.Backdrop />
        <Drawer.Positioner>
          <Drawer.Content>
            <Drawer.Header>
              <Drawer.Title>Node Details</Drawer.Title>
            </Drawer.Header>

            <Drawer.Body>
              {node ? (
                <>
                  <Text><b>ID:</b> {node.id}</Text>
                  <Text><b>Label:</b> {node.data?.label}</Text>
                  <Text><b>Action:</b> {node.data?.action}</Text>

                  {node.data?.conditions && (
                    <>
                      <Text mt={3}><b>Conditions:</b></Text>
                      {node.data.conditions.map((c) => (
                        <Text key={c.id}>• {c.title}</Text>
                      ))}
                    </>
                  )}
                </>
              ) : (
                <Text>No node selected</Text>
              )}
            </Drawer.Body>

            <Drawer.Footer>
              <Button variant="outline" onClick={onClose}>
                Close
              </Button>
            </Drawer.Footer>

            <Drawer.CloseTrigger asChild>
              <CloseButton size="sm" />
            </Drawer.CloseTrigger>
          </Drawer.Content>
        </Drawer.Positioner>
      </Portal>
    </Drawer.Root>
  );
};

export default ActionDrawer;
