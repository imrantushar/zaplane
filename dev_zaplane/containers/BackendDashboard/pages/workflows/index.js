import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import {
  Box,
  Flex,
  Heading,
  Text,
  Button,
  Icon,
} from "@chakra-ui/react";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import { useDispatch, useSelector } from "react-redux";
import ZAPInput from "@ZAPComponents/ZAPInput";
import WPModal from "@ZAPComponents/Modal/WPModal";
import Select from "react-select";
import {
  createWorkflows,
  deleteWorkFlow,
  getWorkFlow,
  updateWorkFlowStatus,
} from "@ZAPRedux/Slices/workFlowSlice/workFlowSlice";
import ZAPTable from "@ZAPComponents/Table";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import TopBar from "@ZAPComponents/TopBar";
import OptionMenu from "@ZAPComponents/OptionMenu";
import { FiEdit, FiTrash2 } from "react-icons/fi";
import {primaryBtn, removeBtn } from "../../../../../assets/scss/chakra/recipe";


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
      <TopBar
        render={() => (
          <Box>
            <Heading className="zaplane-heading">
              {__("Flows", "zaplane")}
            </Heading>
          </Box>
        )}
        rightContent={() => (
          <ZAPMenu
            triggerLabel="Create Workflow"
            items={[
              {
                label: "Create from Scratch",
                onClick: () => setIsModalOpen(true),
              },
              // {
              //   label: "Create with AI",
              //   onClick: () => console.log("AI workflow clicked"),
              // },
            ]}
          />
        )}
      />
      <div className="zaplane-page-content">
        <ZAPTable
          data={data}
          rowKey="id"
          size="sm"
          variant="outline"
          columns={[
            {
              label: __("Title", "zaplane"),
              key: "title",
              textAlign: "center",
              render: (row) => (
                <Text
                onClick={()=>navigate(
                      `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
                    )} fontWeight="500" className="zaplane-label"
                >
                  {__(row.title, "zaplane")}
                </Text>
              ),
            },
            {
              label: __("Created At", "zaplane"),
              key: "created_at",
              textAlign: "center",
              render: (row) => (
                <Text fontSize="sm" className="zaplane-label">
                  {row.created_at}
                </Text>
              ),
            },

            {
              label: __("Updated At", "zaplane"),
              key: "updated_at",
              textAlign: "center",
              render: (row) => (
                <Text fontSize="sm" className="zaplane-label">
                  {__(row.updated_at, "zaplane")}
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
                    menuPortalTarget={document.body}
                    menuPosition="fixed"
                    styles={{
                      menuPortal: (base) => ({
                        ...base,
                        zIndex: 9999,
                      }),
                    }}
                  />
                </Box>
              ),
            },
          ]}
          actionsRenderer={(row) => (
            <OptionMenu
              options={[
                {
                  label: __('Edit', 'zaplane'),
                  icon: <Icon as={FiEdit} />,
                  type: 'button',
                  onClick: () =>
                    navigate(
                      `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${row.id}`
                    ),
                },
                {
                  label: __('Delete', 'zaplane'),
                  suffix: 'trash',
                  icon: <Icon as={FiTrash2} />,
                  type: 'button',
                  onClick: () => workflowDeleteHandler(row.id),
                  hasBorder: false,
                },
              ]}
    
            />
          )}
          isLoading={isLoading}
        />
      </div>
      <WPModal
        title={__("Create Workflow", "zaplane")}
        isOpen={isModalOpen}
        onRequestClose={() => setIsModalOpen(false)}
        size="medium"
      >
        <Box px={4}>
          <ZAPInput
            label={__("Workflow Name", "zaplane")}
            placeholder={__("Enter workflow name", "zaplane")}
            value={workflowName}
            onChange={(e) => setWorkflowName(e.target.value)}
          />

          <Flex justify="flex-end" mt={5}>
            <Button 
            variant="ghost"
             mr={3} onClick={() => setIsModalOpen(false)}>
              {__("Cancel", "zaplane")}
            </Button>
            <Button
              {...primaryBtn}
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
