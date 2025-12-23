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
} from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import { useDispatch, useSelector } from "react-redux";
import LabeledInput from "@ZAPComponents/LabeledInput";
import WPModal from "@ZAPComponents/Modal/WPModal";
import {
  createWorkflows,
  deleteWorkFlow,
  getWorkFlow,
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";

const staticWorkflows = [
  {
    id: 1,
    title: "User Signup Automation",
    name: "signup_flow",
    status: "active",
  },
  {
    id: 2,
    title: "Order Completed",
    name: "order_complete",
    status: "inactive",
  },
  {
    id: 3,
    title: "Email Notification",
    name: "email_notify",
    status: "active",
  },
];

const CreateWorkflows = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [workflowName, setWorkflowName] = useState("");

  const navigate = useNavigate();
  const dispatch = useDispatch();

  // Redux code untouched
  const {data} = useSelector((state) => state.workflows);

console.log(data,'create data');

  useEffect(() => {
    dispatch(getWorkFlow());
  }, [dispatch]);

  const handleCreate = () => {
    if (!workflowName.trim()) return;

    dispatch(
      createWorkflows({
        title: workflowName,
        name: "test",
        status: "active",
        flow_json: JSON.stringify(),
      })
    )
      .unwrap()
      .then((res) => {
        console.log(res,'resData');
        navigate(
           `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.id}`
        );
      });

    setWorkflowName("");
    setIsModalOpen(false);
  };
const workflowDeleteHandler = ( id ) => {
  console.log("id",id);
		if (
			window.confirm(
				__(
					'Are you sure you want to permanently delete ?',
					'zaplane'
				)
			)
		) {
			dispatch( deleteWorkFlow(id) );
		}
	};
  return (
    <>
      {/* ================= HEADER ================= */}
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
          <Heading size="md">{__("Workflows", "zaplane")}</Heading>
          <Text fontSize="sm" color="gray.500">
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
                <Menu.Item>
                  {__("Create with AI", "zaplane")}
                </Menu.Item>
              </Menu.Content>
            </Menu.Positioner>
          </Portal>
        </Menu.Root>
      </Flex>

      {/* ================= TABLE CARD ================= */}
      <Box p={6} bg="gray.50" minH="calc(100vh - 80px)">
        <Box
          bg="white"
          border="1px solid"
          borderColor="gray.200"
          borderRadius="lg"
          boxShadow="sm"
        >
          <Box px={5} py={4} borderBottom="1px solid" borderColor="gray.200">
            <Heading size="sm">
              {__("Workflow List", "zaplane")}
            </Heading>
          </Box>

          <Table.Root size="sm">
            <Table.Header bg="gray.50">
              <Table.Row>
                <Table.ColumnHeader>Title</Table.ColumnHeader>
                <Table.ColumnHeader>Name</Table.ColumnHeader>
                <Table.ColumnHeader>Status</Table.ColumnHeader>
                <Table.ColumnHeader textAlign="end">
                  Actions
                </Table.ColumnHeader>
              </Table.Row>
            </Table.Header>

            <Table.Body>
              {Array.isArray(data) && data?.map((item) => (
                <Table.Row key={item.id} _hover={{ bg: "gray.50" }}>
                  <Table.Cell>
                    <Text fontWeight="500">{item.title}</Text>
                  </Table.Cell>
                  <Table.Cell color="gray.600">
                    {item.name}
                  </Table.Cell>
                  <Table.Cell>
                    <Badge
                      px={2}
                      py={1}
                      borderRadius="md"
                      colorScheme={
                        item.status === "active" ? "green" : "gray"
                      }
                    >
                      {item.status}
                    </Badge>
                  </Table.Cell>
                  <Table.Cell textAlign="end">
                    <Stack direction="row" spacing={2} justify="flex-end">
                      <Button
                        size="xs"
                        variant="outline"
                        onClick={() =>
                          navigate(
                            `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${item.id}`
                          )
                        }
                      >
                        Edit
                      </Button>
                      <Button
                        size="xs"
                        colorScheme="red"
                        variant="ghost"
                        onClick={()=>workflowDeleteHandler(item.id)}
                      >
                        Delete
                      </Button>
                    </Stack>
                  </Table.Cell>
                </Table.Row>
              ))}
            </Table.Body>
          </Table.Root>
        </Box>
      </Box>

      {/* ================= MODAL ================= */}
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
