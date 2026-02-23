
import { __ } from "@wordpress/i18n";
import ZAPSelect from "@ZAPComponents/ZAPSelect";

const SelectTab = ({
  isTrigger,
  actionOptions,
  values,
  setFieldValue,
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
        containerStyle={{ marginBottom: "8px" }}
      />
    </>
  );
};

export default SelectTab;