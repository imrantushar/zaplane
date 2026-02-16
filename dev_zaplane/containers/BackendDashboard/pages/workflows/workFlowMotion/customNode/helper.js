export function formatLabel(value = "") {

  return value
    .replace(/_/g, " ")
    .toLowerCase()
    .replace(/\b\w/g, char => char.toUpperCase());
}
