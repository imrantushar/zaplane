import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import {
  Box,
  Flex,
  Heading,
  Text,
  Button,
  Badge,
  Table,
  Stack,
  Menu,
  Portal,
  Spinner,

} from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import { useDispatch, useSelector } from "react-redux";
import LabeledInput from "@ZAPComponents/LabeledInput";
import WPModal from "@ZAPComponents/Modal/WPModal";
import Select from "react-select";
import {
  createWorkflows,
  deleteWorkFlow,
  getWorkFlow,
  updateWorkFlow,
  updateWorkFlowStatus,
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import { FiMoreVertical } from "react-icons/fi";
import { showNotification } from "@ZAPRedux/Slices/notificationSlice/notificationSlice";
import ZAPLoading from "@ZAPComponents/Loading";
import ZAPTable from "@ZAPComponents/Table";


const CreateWorkflows = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [workflowName, setWorkflowName] = useState("");

  const navigate = useNavigate();
  const dispatch = useDispatch();

  const { data, isLoading } = useSelector((state) => state.workflows);

  useEffect(() => {
    dispatch(getWorkFlow());
  }, [dispatch]);

  const handleCreate = () => {
    if (!workflowName.trim()) return;

    dispatch(
      createWorkflows({
        title: workflowName
      })
    )
      .unwrap()
      .then((res) => {
        navigate(
          `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.id}`
        );
      });

    setWorkflowName("");
    setIsModalOpen(false);
  };
  const workflowDeleteHandler = (id) => {
    if (
      window.confirm(
        __(
          'Are you sure you want to permanently delete ?',
          'zaplane'
        )
      )
    ) {
      dispatch(deleteWorkFlow(id));
    }
  };
  const statusOptions = [
    { value: "active", label: "Active" },
    { value: "paused", label: "Paused" },
    { value: "draft", label: "draft" },
  ];
  const onSubmitHandler = async (item, status) => {
    if (!item?.id || !status) return;

    const payload = {
      status: status,
      id: item.id,
    };

    try {
      await dispatch(updateWorkFlowStatus(payload));
    } catch (error) {
      console.log(error);
    }
  };


  return (
    <>
      <Flex
        px={6}
        py={4}
        align="center"
        justify="space-between"
        borderBottom="1px solid"
        borderColor="gray.200"
        bg="white"
      >
        <Box>
          <Heading margin='0' size="md">{__("Workflows", "zaplane")}</Heading>
          <Text fontSize="sm" margin='0' color="gray.500">
            {__("Automate actions between your apps", "zaplane")}
          </Text>
        </Box>

        <Menu.Root>
          <Menu.Trigger asChild>
            <Button colorScheme="blue">
              {__("Create Workflow", "zaplane")}
            </Button>
          </Menu.Trigger>
          <Portal>
            <Menu.Positioner>
              <Menu.Content>
                <Menu.Item onClick={() => setIsModalOpen(true)}>
                  {__("Create from Scratch", "zaplane")}
                </Menu.Item>
                {/* <Menu.Item>
                  {__("Create with AI", "zaplane")}
                </Menu.Item> */}
              </Menu.Content>
            </Menu.Positioner>
          </Portal>
        </Menu.Root>
      </Flex>
      <Box p={6} bg="gray.50" minH="calc(100vh - 80px)">
        <Box
          bg="white"
          border="1px solid"
          borderColor="gray.200"
          borderRadius="lg"
          boxShadow="sm"
        >
          <Box px={5} py={4} borderBottom="1px solid" borderColor="gray.200">
            <Heading size="sm" margin='0'>
              {__("Workflow List", "zaplane")}
            </Heading>
          </Box>

          <ZAPTable
            data={data}
            rowKey="id"
            size="sm"
            variant="line"
            columns={[
              {
                label: __("Title", "zaplane"),
                key: "title",
                textAlign: "center",
                render: (row) => (
                  <Text fontWeight="500" m={0}>
                    {row.title}
                  </Text>
                ),
              },
              {
                label: __("Created At", "zaplane"),
                key: "created_at",
                textAlign: "center",
                render: (row) => (
                  <Text fontSize="sm">
                    {row.created_at}
                  </Text>
                ),
              },

              {
                label: __("Updated At", "zaplane"),
                key: "updated_at",
                textAlign: "center",
                render: (row) => (
                  <Text fontSize="sm">
                    {row.updated_at}
                  </Text>
                ),
              },
                {
                label: __("Status", "zaplane"),
                key: "status",
                textAlign: "center",
                render: (row) => (
                  <Box w="120px" mx="auto">
                    <Select
                      options={statusOptions}
                      value={statusOptions.find(
                        (opt) => opt.value === row.status
                      )}
                      onChange={(selected) =>
                        onSubmitHandler(row, selected.value)
                      }
                      isClearable={false}
                      isSearchable={false}
                    />
                  </Box>
                ),
              },
            ]}
            actionsRenderer={(row) => (
              <Box>
                <Button
                  size="xs"
                  variant="outline"
                  onClick={() =>
                    navigate(
                      `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
                    )
                  }
                >
                  Edit
                </Button>

                <Button
                  size="xs"
                  colorScheme="red"
                  variant="ghost"
                  onClick={() => workflowDeleteHandler(row.id)}
                >
                  Delete
                </Button>
              </Box>
            )}
            isLoading={isLoading}
          />
        </Box>
      </Box>
      <WPModal
        title={__("Create Workflow", "zaplane")}
        isOpen={isModalOpen}
        onRequestClose={() => setIsModalOpen(false)}
        size="medium"
      >
        <Box px={4}>
          <LabeledInput
            label={__("Workflow Name", "zaplane")}
            placeholder={__("Enter workflow name", "zaplane")}
            value={workflowName}
            onChange={(e) => setWorkflowName(e.target.value)}
          />

          <Flex justify="flex-end" mt={5}>
            <Button variant="ghost" mr={3} onClick={() => setIsModalOpen(false)}>
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
