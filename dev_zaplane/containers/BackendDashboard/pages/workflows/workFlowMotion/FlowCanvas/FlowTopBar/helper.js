export const downloadJSON = (data, fileName = "workflow") => {
  if (!data) return;

  const cleanName = fileName
    .replace(/\s+/g, "-")
    .toLowerCase();

  const blob = new Blob([JSON.stringify(data, null, 2)], {
    type: "application/json",
  });

  const url = URL.createObjectURL(blob);

  const a = document.createElement("a");
  a.href = url;
  a.download = `${cleanName}.json`;
  a.click();

  URL.revokeObjectURL(url);
};