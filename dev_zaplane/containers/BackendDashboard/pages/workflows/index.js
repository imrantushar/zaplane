import { useState } from "react";
import { __ } from "@wordpress/i18n";
import {
  Button,
  Menu,
  Portal,
  Box,
  Flex,
  Heading,
  Text,
  Stack
} from "@chakra-ui/react";
import LabeledInput from "@ZAPComponents/LabeledInput";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import { useDispatch } from "react-redux";
import { createWorkflowTitle } from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";

const CreateWorkflows = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [workflowName, setWorkflowName] = useState("");
  const navigate = useNavigate();
  const dispatch = useDispatch();

  const handleCreate = () => {
    if (!workflowName.trim()) return;

    navigate(
      `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${Date.now()}`
    );
     dispatch(createWorkflowTitle(workflowName));
    setWorkflowName("");
    setIsModalOpen(false);
  };

  return (
    <>
      <Flex
        minH="calc(100vh - 60px)"
        align="center"
        justify="center"
        bg="gray.50"
        px={6}
      >
        <Box
          maxW="520px"
          w="100%"
          bg="white"
          borderRadius="lg"
          boxShadow="sm"
          border="1px solid"
          borderColor="gray.200"
          p={8}
          textAlign="center"
        >
          <Stack spacing={4}>
            <Text fontSize="3xl">⚡</Text>

            <Heading size="md">
              {__("Create your first workflow", "zaplane")}
            </Heading>

            <Text fontSize="sm" color="gray.600">
              {__(
                "Workflows let you automate actions between apps. Get started by creating a new workflow.",
                "zaplane"
              )}
            </Text>

            {/* ===== Menu Trigger ===== */}
            <Menu.Root >
              <Menu.Trigger asChild>
                <Button colorScheme="blue" size="md">
                  {__("Create Work Flow", "zaplane")}
                </Button>
              </Menu.Trigger>

              <Portal>
                <Menu.Positioner>
                  <Menu.Content width="460px">
                    <Menu.Item
                      value="new-scratch"
                      onClick={() => setIsModalOpen(true)}
                    >
                      {__("Create New Scratch", "zaplane")}
                    </Menu.Item>

                    <Menu.Item value="ai">
                      {__("Create with AI", "zaplane")}
                    </Menu.Item>
                  </Menu.Content>
                </Menu.Positioner>
              </Portal>
            </Menu.Root>
          </Stack>
        </Box>
      </Flex>
      <WPModal
        title={__("Create Workflow", "zaplane")}
        isOpen={isModalOpen}
        onRequestClose={() => setIsModalOpen(false)}
        size="medium"
      >
        <Box px={4}>
          <Box mb={4}>
            <LabeledInput
              label={__("Workflow Name", "zaplane")}
              placeholder={__("Enter workflow name", "zaplane")}
              value={workflowName}
              onChange={(e) => setWorkflowName(e.target.value)}
              style={{ width: "100%" }}
            />
          </Box>

          <Flex justifyContent="flex-end">
            <Button
              variant="ghost"
              mr={3}
              onClick={() => setIsModalOpen(false)}
            >
              {__("Cancel", "zaplane")}
            </Button>

            <Button
              colorScheme="blue"
              onClick={handleCreate}
              isDisabled={!workflowName.trim()}
            >
              {__("Create", "zaplane")}
            </Button>
          </Flex>
        </Box>
      </WPModal>
    </>
  );
};

export default CreateWorkflows;
