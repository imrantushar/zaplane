export const buildEmptyRow = (fields) => {
  const row = {};
  (fields || []).forEach(f => {
    row[f.key] = f.type === "select"
      ? f.options?.[0]?.value ?? ""
      : f.type === "boolean" || f.type === "checkbox"
        ? (f.default ?? false)
        : "";
  });
  return row;
};
