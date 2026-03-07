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

    let baseValue = currentRule[fieldKey] || "";
    if (baseValue.endsWith("@")) baseValue = baseValue.slice(0, -1);

    // Append selected variable remove space " "
    const newValue = baseValue ? `${baseValue}${valueToInsert}` : valueToInsert;

    newGroups[gIndex][rIndex] = {
        ...currentRule,
        [fieldKey]: newValue,
    };
    groupHelpers.replace(gIndex, newGroups[gIndex]);
    setPopoverOpen(false);
    setActiveInput(null);
}
export const formatVariableKey = (key) => {
  if (!key) return "";

  return key
    .split(".")                
    .pop()                     
    .replace(/\[\]/g, "")       
    .replace(/_/g, " ")       
    .replace(/\b\w/g, (c) => c.toUpperCase()); 
};
