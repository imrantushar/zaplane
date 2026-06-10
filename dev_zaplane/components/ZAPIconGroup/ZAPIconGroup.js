import { plugin_root_url } from "@ZAPUtils/helper";
const ZAPIconGroup = ({
  icons = [],
  maxVisible = 2
}) => {
  const safeIcons = (Array.isArray(icons) ? icons : []).filter(Boolean);
  const visibleIcons = safeIcons.slice(0, maxVisible);
  const remaining = Math.max(0, safeIcons.length - maxVisible);

  if (safeIcons.length === 0) {
    return <img src={`${plugin_root_url}assets/images/button.svg`} style={{ width: '40px', height: '40px' }} />;
  }

  return <div className="flex border border-[#E5E7EB] rounded-[4px] overflow-hidden bg-white w-fit">
    {visibleIcons.map((icon, index) => {
      const isSvg = icon?.endsWith(".svg");
      const isLast = index === visibleIcons.length - 1 && remaining === 0;

      return <div key={index} style={{
        borderRight: isLast ? 'none' : '1px solid #E5E7EB',
        padding: '4px 10px'
      }} className="flex h-[36px] min-w-[40px] justify-center items-center bg-[#F9FAFB]">
        {isSvg ? <img src={`${plugin_root_url}assets/images/icons/${icon}`} alt={icon} style={{
          width: '20px',
          height: '20px',
          objectFit: 'contain'
        }} /> : <span className={`zaplane-icon zaplane-icon--${icon} m-0`} />}
      </div>;
    })}

    {remaining > 0 && <div className="flex px-3 h-[36px] justify-center items-center bg-[#F9FAFB] min-w-[40px]">
      <span className="zaplane-sub-title">
        +{remaining}
      </span>
    </div>}
  </div>;
};

export default ZAPIconGroup;