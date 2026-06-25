import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";
import { __ } from "@wordpress/i18n";
import ActionFieldRenderer from "../ActionFieldRenderer/ActionFieldRenderer";
import { buildEmptyRow } from "./helper";
import { primaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";

// Shared renderer for the `map` and `repeater` field types: a Formik FieldArray
// of rows, where each row renders its sub-fields via ActionFieldRenderer itself
// so any already-supported field type works inside a row for free.
export default function RepeaterField({
  field,
  value,
  getKey,
  dynamicOptions,
  loadingFields,
  fetchDynamicOptions
}) {
  const rowFields = field?.fields || [];
  const EMPTY_ROW = buildEmptyRow(rowFields);

  return (
    <FieldArray name={field.key}>
      {(helpers) => {
        if (!value || !value.length) {
          helpers.push({ ...EMPTY_ROW });
        }
        const rows = value || [];

        return (
          <div className="flex flex-col gap-3">
            {field.label && (
              <span className="zaplane-label">
                {__(field.label, "zaplane")}
                {field.required && <span style={{ color: "red", marginLeft: "2px" }}>*</span>}
              </span>
            )}

            {rows.map((row, rIndex) => (
              <div key={rIndex} className="flex items-start gap-4">
                {rowFields.map((rf) => (
                  <ActionFieldRenderer
                    key={rf.key}
                    field={{ ...rf, key: `${field.key}.${rIndex}.${rf.key}` }}
                    value={row?.[rf.key]}
                    setFieldValue={(_key, val) => helpers.replace(rIndex, { ...row, [rf.key]: val })}
                    getKey={getKey}
                    dynamicOptions={dynamicOptions || {}}
                    loadingFields={loadingFields || {}}
                    fetchDynamicOptions={fetchDynamicOptions}
                  />
                ))}

                <button
                  type="button"
                  className="mt-[27px] disabled:opacity-40"
                  disabled={rows.length === 1}
                  onClick={() => helpers.remove(rIndex)}
                >
                  <FiTrash2 />
                </button>
              </div>
            ))}

            <button type="button" style={primaryBtn} onClick={() => helpers.push({ ...EMPTY_ROW })}>
              {__("Add Row", "zaplane")}
            </button>
          </div>
        );
      }}
    </FieldArray>
  );
}
