export const buildEmptyRule = (fields) => {
  const rule = {};
  fields.forEach(f => {
    rule[f.key] = f.type === "select"
      ? f.options?.[0]?.value ?? ""
      : "";
  });
  return rule;
};
