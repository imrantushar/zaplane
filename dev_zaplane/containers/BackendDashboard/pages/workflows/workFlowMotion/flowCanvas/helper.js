export const createNodeIdGenerator = () => {
  let current = 0;
  return () => {
    current += 1;
    return `${current}`;
  };
};
export const mapGraphFromBackend = (graph) => {
  // if (!graph) return { nodes: [], edges: [] };
  return {
    nodes: (graph.nodes || []).map((node) => ({
      ...node,
      type: "custom",
      data: {
        ...node.data,
        action: node.type,
      },
    })),
    edges: (graph.edges || []).map((edge) => ({
      ...edge,
      type: "custom",
    })),
  };
};
export function applyStyles(el, styles = {}) {
  if (!el) return;
  Object.entries(styles).forEach(([key, value]) => {
    el.style[key] = value;
  });
};

export const toggleFullscreenMode = (containerRef, isFullscreen, setIsFullscreen) => {
  if (!containerRef.current) return;
  const elem = containerRef.current;
  const adminMenu = document.getElementById("adminmenu");
  const wpSubMenus = document.querySelectorAll("#adminmenu .wp-submenu");
  const wpWrap = document.getElementById("wpwrap");
  const wpAdminBar = document.getElementById("wpadminbar");
  const adminMenuBack = document.getElementById("adminmenuback");
  const adminMenuWrap = document.getElementById("adminmenuwrap");

  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
  } else {
    document.exitFullscreen();
  }

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
  applyStyles(wpAdminBar, { display: isEnter ? "none" : "" });

  setIsFullscreen(isEnter);
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
