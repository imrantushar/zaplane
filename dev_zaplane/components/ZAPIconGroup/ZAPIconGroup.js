import { plugin_root_url } from "@ZAPUtils/helper";
const ZAPIconGroup = ({
  icons = [],
  maxVisible = 3
}) => {
  const safeIcons = Array.isArray(icons) ? icons : [];
  const visibleIcons = safeIcons.slice(0, maxVisible);
  const remaining = Math.max(0, safeIcons.length - maxVisible);
  const totalItems = visibleIcons.length + (remaining > 0 ? 1 : 0);
  if (safeIcons.length === 0) {
    return <img src={`${plugin_root_url}assets/images/button.svg`} style={{width:'40px', height:'40px'}} />;
  }
  return <div style={{overflow:'hidden'}} className="flex border rounded-[6px]">
            {visibleIcons.map((icon, index) => {
      const isSvg = icon?.endsWith(".svg");
      const isLast = index === totalItems - 1;
      return <div key={index} style={{borderRight: isLast ? 'none' : '1px solid var(--zaplane-border-color)', background:'var(--zaplane-background)', padding:'4px 8px'}} className="flex h-[32px] w-[36px] justify-center items-center">
                        {isSvg ? <img src={`${plugin_root_url}assets/images/icons/${icon}`} alt={icon} style={{width:'20px',height:'20px'}} /> : <span className={`zaplane-icon zaplane-icon--${icon} m-0`} />}
                    </div>;
    })}

            {remaining > 0 && <div className="flex w-[36px] h-[32px] justify-center items-center bg-var(--zaplane-background)">
                    <span className="zapane-title font-[400] text-var(--zaplane-text-muted, #888) m-0">
                        +{remaining}
                    </span>
                </div>}
        </div>;
};
export default ZAPIconGroup;