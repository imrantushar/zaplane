import { FieldArray } from "formik";
import { FiTrash2 } from "react-icons/fi";
import ZAPSelect from "@ZAPComponents/ZAPSelect";
import { __ } from "@wordpress/i18n";
import { buildEmptyRule } from "./helper";
import VariableEditor from "@ZAPComponents/VariableEditor";
export default function ConditionGroupField({
  value,
  field,
  variables
}) {
  const ruleFields = field?.fields;
  const EMPTY_RULE = buildEmptyRule(ruleFields);
  return <FieldArray name={field.key}>
            {groupHelpers => {
      if (!value || !value.length) {
        groupHelpers.push([{
          ...EMPTY_RULE
        }]);
      }
      const groups = value || [];
      return <div className="flex flex-col gap-4">
                        {groups.map((group, gIndex) => <div key={gIndex}>
                                {groups.length > 1 && gIndex !== 0 && <div className="flex items-center mb-3">
                                        <div className="flex-[1] h-[1px] bg-gray-300" />
                                        <span className="mx-3 text-[sm]">
                                            {__("OR", "zaplane")}
                                        </span>
                                        <div className="flex-[1] h-[1px] bg-gray-300" />
                                    </div>}

                                <FieldArray name={`${field.key}.${gIndex}`}>
                                    {ruleHelpers => <>
                                            {group.map((rule, rIndex) => <div key={rIndex} className="flex items-start gap-4 mb-[15px]">
                                                    {ruleFields.map(f => {
                  if (f.type === "select") {
                    return <ZAPSelect key={f.key} label={f.label} options={f.options} value={rule[f.key]} onChange={val => ruleHelpers.replace(rIndex, {
                      ...rule,
                      [f.key]: val.value
                    })} containerStyle={{
                      width: "30%"
                    }} />;
                  }
                  return <VariableEditor containerStyle={{
                    width: '30%'
                  }} label={f.label} placeholder={__('Type "@" here to...', "zaplane")} value={rule[f.key]} variables={variables} field={{
                    key: `${field.key}.${gIndex}.${rIndex}.${f.key}`
                  }} setFieldValue={(key, val) => {
                    ruleHelpers.replace(rIndex, {
                      ...rule,
                      [f.key]: val
                    });
                  }} />;
                })}

                                                    <div className="flex items-center gap-2 mt-[27px] min-h-[30px]">
                                                        <button type="button" style={{height:'34px', background:'var(--zaplane-secondary)', color:'var(--zaplane-font-color)'}} onClick={() => ruleHelpers.push({
                    ...EMPTY_RULE
                  })} className="bg-var(--zaplane-secondary) text-var(--zaplane-font-color)">
                                                            {__("Add", "zaplane")}
                                                        </button>

                                                        <button type="button" variant="ghost" size="sm" disabled={group.length === 1 && groups.length === 1 && !gIndex} onClick={() => {
                    if (group.length === 1) {
                      groupHelpers.remove(gIndex);
                    } else {
                      ruleHelpers.remove(rIndex);
                    }
                  }}>
                                                            <FiTrash2 />
                                                        </button>
                                                    </div>
                                                </div>)}
                                        </>}
                                </FieldArray>
                            </div>)}

                        <button size="sm" width="140px" onClick={() => groupHelpers.push([{
          ...EMPTY_RULE
        }])} className="bg-var(--zaplane-secondary) text-var(--zaplane-font-color) font-[500]">
                            {__("OR Group", "zaplane")}
                        </button>
                    </div>;
    }}
        </FieldArray>;
}