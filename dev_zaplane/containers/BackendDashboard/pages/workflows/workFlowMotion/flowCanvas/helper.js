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
  const wpContent = document.getElementById("wpcontent");
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
    // Without an explicit background, the fixed container is transparent, so the
    // strip that used to sit under #adminmenu (now hidden) shows the unstyled
    // page behind it instead of the theme background — visible as a white/light
    // band on the left in dark mode.
    backgroundColor: isEnter ? "var(--zaplane-body-background)" : "",
  });

  // Hide WordPress menu elements. `width: 0` alone can leave a visible box —
  // #adminmenuback/#adminmenuwrap are WP-core-styled fixed-position columns
  // that don't necessarily collapse to zero width from an inline override —
  // so every element here gets `display: none`, not just #adminmenu.
  const menuElements = [adminMenu, adminMenuBack, adminMenuWrap, ...wpSubMenus];
  menuElements.forEach(el => {
    if (!el) return;
    applyStyles(el, {
      width: isEnter ? "0" : "",
      backgroundColor: isEnter ? "transparent" : "",
      display: isEnter ? "none" : "",
    });
  });

  // Adjust wpWrap & wpAdminBar
  applyStyles(wpWrap, { marginLeft: isEnter ? "0" : "" });
  applyStyles(wpAdminBar, { display: isEnter ? "none" : "" });

  // #wpcontent carries its own margin-left (independent of #wpwrap) in WP core
  // CSS to make room for #adminmenu; reset it too so nothing but our own
  // themed container occupies that space in fullscreen.
  applyStyles(wpContent, { marginLeft: isEnter ? "0" : "" });

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
