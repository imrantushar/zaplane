export const statusOptions = [
    { value: "active", label: "Active" },
    { value: "paused", label: "Paused" },
    { value: "draft", label: "Draft" },
];
export const statusStyle = (status) => {
  switch (status) {
    case "completed":
      return {
        color: "#16A34A",
        bg: "#DCFCE7",
      };

    case "running":
      return {
        color: "#2563EB",
        bg: "#DBEAFE",
      };

    case "failed":
      return {
        color: "#DC2626",
        bg: "#FEE2E2",
      };

    default:
      return {
        color: "#4B5563", 
        bg: "#F3F4F6",
      };
  }
};
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