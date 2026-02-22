import { Flex } from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ActionFieldRenderer from "../ActionFieldRenderer/ActionFieldRenderer";

const SelectTab = ({
  isTrigger,
  actionOptions,
  selectedActionFields,
  values,
  setFieldValue,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions,
  getKey,
  node,
  workFlow,
}) => {
  return (
    <>
      <ZAPSelect
        label={
          isTrigger
            ? __("Trigger Type", "zaplane")
            : __("Action Type", "zaplane")
        }
        options={actionOptions}
        value={values?.actionType}
        onChange={(val) => {
          setFieldValue("actionType", val?.value);
          setFieldValue("hook", val?.hook);
        }}
        placeholder={__("Select Action Type", "zaplane")}
        isClearable
        mb={4}
      />

      <Flex direction="column" gap={4}>
        {selectedActionFields?.map((field) => (
          <ActionFieldRenderer
            key={field.key}
            field={field}
            value={values?.[field.key]}
            setFieldValue={setFieldValue}
            getKey={getKey}
            dynamicOptions={dynamicOptions}
            loadingFields={loadingFields}
            fetchDynamicOptions={fetchDynamicOptions}
            nodeId={node?.id}
            workFlow={workFlow}
          />
        ))}
      </Flex>
    </>
  );
};

export default SelectTab;