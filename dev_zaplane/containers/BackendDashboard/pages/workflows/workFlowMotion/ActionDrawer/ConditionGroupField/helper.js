export const buildEmptyRule = (fields) => {
  const rule = {};
  fields.forEach(f => {
    rule[f.key] = f.type === "select"
      ? f.options?.[0]?.value ?? ""
      : "";
  });
  return rule;
};
export const insertVariableIntoGroup=({
    activeInput,
    groups,
    groupHelpers,
    valueToInsert,
    setPopoverOpen,
    setActiveInput,
})=> {
    if (!activeInput) return;

    const { gIndex, rIndex, fieldKey } = activeInput;
    const newGroups = [...groups];
    const currentRule = newGroups[gIndex][rIndex];

    // Remove last '@' if present
    let baseValue = currentRule[fieldKey] || "";
    if (baseValue.endsWith("@")) baseValue = baseValue.slice(0, -1);

    // Append selected variable
    const newValue = baseValue ? `${baseValue}, ${valueToInsert}` : valueToInsert;

    newGroups[gIndex][rIndex] = {
        ...currentRule,
        [fieldKey]: newValue,
    };

    // Update Formik state
    groupHelpers.replace(gIndex, newGroups[gIndex]);

    // Close popover and reset active input
    setPopoverOpen(false);
    setActiveInput(null);
}