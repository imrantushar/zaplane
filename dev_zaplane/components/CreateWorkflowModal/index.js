import { useEffect, useState, useMemo } from "react";
import { Box, Flex, Button, Text } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";

import WPModal from "@ZAPComponents/Modal/WPModal";
import ZAPInput from "@ZAPComponents/ZAPInput";
import { createWorkflows } from "@ZAPRedux/Slices/workFlowSlice/actions/workFlow";
import { route_path } from "@ZAPUtils/helper";
import { primaryBtn } from "../../../assets/scss/chakra/recipe";
import ZAPDivider from "@ZAPComponents/ZAPDivider";
import { getFolders } from "@ZAPRedux/Slices/folderSlice/folderSlice";
import Select from "react-select";

const CreateWorkflowModal = ({ isOpen, onClose, id }) => {
  const dispatch = useDispatch();
  const navigate = useNavigate();

  const [workflowName, setWorkflowName] = useState("");
  const [folderId, setFolderId] = useState(null);

  const { folders } = useSelector((state) => state.folder);
  const allFolders = folders?.data || [];

  useEffect(() => {
    dispatch(getFolders());
  }, [dispatch]);

  const effectiveFolderId = folderId ?? id ?? null;

  const options = useMemo(() => {
    return allFolders.map((f) => ({
      label: f.title,
      value: f.id,
    }));
  }, [allFolders]);

  const selectedOption = useMemo(() => {
    return options.find((opt) => opt.value === effectiveFolderId) || null;
  }, [options, effectiveFolderId]);

  const handleCreate = async () => {
    if (!workflowName.trim()) return;

    const payload = {
      title: workflowName,
      ...(effectiveFolderId && { folderId: effectiveFolderId }),
    };

    const res = await dispatch(createWorkflows(payload));

    if (res?.payload?.id) {
      navigate(
        `${route_path}admin.php?page=zaplane-workflows&action=edit&id=${res.payload.id}`
      );
    }

    setWorkflowName("");
    setFolderId(null);
    onClose();
  };

  return (
    <WPModal
      title={__("Create Workflow", "zaplane")}
      isOpen={isOpen}
      onRequestClose={onClose}
      size="medium"
    >
      <ZAPDivider mb="24px" />

      <Flex flexDirection="column" gap="8px">
        <ZAPInput
          label={__("Workflow Name", "zaplane")}
          placeholder={__("Enter workflow name", "zaplane")}
          value={workflowName}
          onChange={(e) => setWorkflowName(e.target.value)}
        />
        {!!allFolders.length &&
          (
            <Flex direction="column" gap={2}>
            <Text className="zaplane-label">
              {__("Select Folder", "zaplane")}
            </Text>

            <Select
              options={options}
              value={selectedOption}
              onChange={(val) => setFolderId(val?.value || null)}
              styles={{
                menu: (base) => ({
                  ...base,
                  position: "static",
                }),
              }}
            />
          </Flex>
          )
        }



        <ZAPDivider mt="24px" />

        <Flex justify="flex-start" mt={5}>
          <Button variant="outline" mr={3} onClick={onClose}>
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
      </Flex>
    </WPModal>
  );
};

export default CreateWorkflowModal;