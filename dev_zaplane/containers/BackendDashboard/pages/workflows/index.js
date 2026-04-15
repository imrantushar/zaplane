import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
import { Box, Flex, Heading, Button } from "@chakra-ui/react";
import { useDispatch, useSelector } from "react-redux";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import TopBar from "@ZAPComponents/TopBar";
import ZAPInput from "@ZAPComponents/ZAPInput";
import WPModal from "@ZAPComponents/Modal/WPModal";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";

import {
  createWorkflows,
  getWorkFlow,
} from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import WorkflowTable from "./WorkflowTable";
import { useNavigate } from "react-router-dom";
import { route_path } from "@ZAPUtils/helper";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";


const CreateWorkflows = ({ onNavigateToEdit, title = __('Flows', 'zaplane')}) => {
  const dispatch = useDispatch();
  const navigate =useNavigate()
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [workflowName, setWorkflowName] = useState("");

   const handleCreate = async () => {
    if (!workflowName.trim()) return;
    const res = await dispatch(
      createWorkflows({
        title: workflowName
      })
    )
    if (res?.payload.id) {
      if (onNavigateToEdit) {
        onNavigateToEdit(res.payload.id);
      } else {
        navigate(
          `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.payload.id}`
        );
      }
    }

    setWorkflowName("");
    setIsModalOpen(false);
  };



  return (
    <>
      <TopBar
        render={() => (
          <Box>
            <ZAPLabel
              label={title}
              variant="bold"
            />

          </Box>
        )}
        rightContent={() => (
          <Button {...primaryBtn} onClick={() => setIsModalOpen(true)}>
            {__('Create Workflow', 'zaplane')}
          </Button>
        )}
      />

      <div className="zaplane-page-content">
        <WorkflowTable onNavigateToEdit={onNavigateToEdit}
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
              variant="outline"
              mr={3}
              onClick={() => setIsModalOpen(false)}
            >
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
