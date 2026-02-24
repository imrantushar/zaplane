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
  const topBar = document.querySelector(".zaplane-topbar");
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
  } else {
    document.exitFullscreen();
  }

  const isEnter = !isFullscreen;

  // Apply styles to the main container
  applyStyles(elem, {
    position: isEnter ? "fixed" : "",
    top: isEnter ? "0" : "",
    left: isEnter ? "0" : "",
    width: isEnter ? "-webkit-fill-available" : "",
    height: isEnter ? "100vh" : "",
    zIndex: isEnter ? "9999" : "",
  });

  // Hide WordPress menu elements
  const menuElements = [adminMenu, adminMenuBack, adminMenuWrap, ...wpSubMenus];
  menuElements.forEach(el => {
    if (!el) return;
    applyStyles(el, {
      width: isEnter ? "0" : "",
      backgroundColor: isEnter ? "transparent" : "",
      display: el === adminMenu && isEnter ? "none" : el === adminMenu && !isEnter ? "" : "",
    });
  });

  // Adjust wpWrap & wpAdminBar
  applyStyles(wpWrap, { marginLeft: isEnter ? "0" : "" });
  applyStyles(wpAdminBar, { display: isEnter ? "none" : "" });

  // Adjust zaplane-topbar
  if (topBar) {
    applyStyles(topBar, {
      // position: isEnter ? "fixed" : "",
      top: isEnter ? "0" : "",
      left: isEnter ? "0" : "",
      width: isEnter ? "100%" : "",
      zIndex: isEnter ? "10000" : "",
    });
  }

  setIsFullscreen(isEnter);
};


//lisenet timer 
export const formatTime = (seconds) => {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${s.toString().padStart(2, "0")}`;
};
