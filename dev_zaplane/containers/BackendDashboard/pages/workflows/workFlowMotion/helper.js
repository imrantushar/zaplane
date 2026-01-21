export const toggleFullscreenMode = (containerRef, isFullscreen, setIsFullscreen) => {
  if (!containerRef.current) return;
  const elem = containerRef.current;
  const adminMenu = document.getElementById("adminmenu");
  const wpSubMenus = document.querySelectorAll("#adminmenu .wp-submenu");
  const wpWrap = document.getElementById("wpwrap");
  const wpAdminBar = document.getElementById("wpadminbar");
  const adminMenuBack = document.getElementById("adminmenuback");
  const adminMenuWrap = document.getElementById("adminmenuwrap");
  const applyStyles = (el, styles = {}) => {
    if (!el) return;
    Object.entries(styles).forEach(([key, value]) => {
      el.style[key] = value;
    });
  };

  const isEnter = !isFullscreen;
  applyStyles(elem, {
    position: isEnter ? "fixed" : "",
    top: isEnter ? "0" : "",
    left: isEnter ? "0" : "",
    width: isEnter ? "-webkit-fill-available" : "",
    height: isEnter ? "100vh" : "",
    zIndex: isEnter ? "9999" : "",
  });

  const menuElements = [adminMenu, adminMenuBack, adminMenuWrap, ...wpSubMenus];
  menuElements.forEach(el => {
    applyStyles(el, {
      width: isEnter ? "0" : "",
      backgroundColor: isEnter ? "transparent" : "",
      display: el === adminMenu && isEnter ? "none" : el === adminMenu && !isEnter ? "" : "",
    });
  });

  applyStyles(wpWrap, { marginLeft: isEnter ? "0" : "" });
  applyStyles(wpAdminBar, { top: isEnter ? "0" : "" });

  setIsFullscreen(isEnter);
};
export const getDuration = (start, end) => {
  if (!start || !end) return "--";

  const startTime = new Date(start.replace(" ", "T"));
  const endTime = new Date(end.replace(" ", "T"));

  if (isNaN(startTime) || isNaN(endTime)) return "--";

  const diffMs = endTime - startTime;
  const seconds = Math.floor(diffMs / 1000);
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;

  if (mins > 0) {
    return `${mins}m ${secs}s`;
  }

  return `${secs}s`;
};
export const mapNodesForBackend = (nodes) => {
  return nodes.map(({
    dragging,
    selected,
    measured,
    data,
    ...node
  }) => {
    const backendType = data?.action?.toLowerCase();
    const cleanedData = { ...data };
    delete cleanedData.action;

    return {
      ...node,
      type: backendType,
      data: cleanedData,
    };
  });
};
export const mapEdgesForBackend = (edges) => {
  return edges.map(({ type, ...edge }) => edge);
};
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

export const buildEmptyRule = (fields) => {
  const rule = {};
  fields.forEach(f => {
    rule[f.key] = f.type === "select"
      ? f.options?.[0]?.value ?? ""
      : "";
  });
  return rule;
};
