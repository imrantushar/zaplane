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
    width: isEnter ? "100vw" : "",
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
