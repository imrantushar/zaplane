
import { __ } from "@wordpress/i18n";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import ConnectionSelector from "./ConnectionSelector/ConnectionSelector";

const SelectTab = ({
  isTrigger,
  actionOptions,
  values,
  setFieldValue,
  selectedIntegration,
  appSlug,
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
        isRequired
        containerStyle={{ marginBottom: "8px" }}
      />
      {selectedIntegration?.requires_connection === true && (
        <ConnectionSelector
          appSlug={appSlug}
          values={values}
          setFieldValue={setFieldValue}
          selectedIntegration={selectedIntegration}
        />
      )}
    </>
  );
};

export default SelectTab;