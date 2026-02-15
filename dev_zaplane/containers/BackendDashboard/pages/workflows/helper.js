export const statusOptions = [
    { value: "active", label: "Active" },
    { value: "paused", label: "Paused" },
    { value: "draft", label: "draft" },
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