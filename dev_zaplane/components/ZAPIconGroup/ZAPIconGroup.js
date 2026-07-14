import { plugin_root_url, integrations } from "@ZAPUtils/helper";

// Custom apps store a full icon URL (or data URI); built-ins store a bare filename.
// Absolute sources must be used verbatim rather than resolved/prefixed.
export const isAbsoluteIcon = (icon) =>
  typeof icon === "string" && (/^https?:\/\//.test(icon) || icon.startsWith("data:") || icon.startsWith("//"));

// Some callers (e.g. recipe seeders) store a bare integration slug (e.g. "woocommerce")
// instead of the actual icon filename (e.g. "woo.svg"). Resolve those against the
// integrations manifest so stale/legacy values still render correctly.
export const resolveIconFilename = (icon) => {
  if (!icon) return icon;
  if (isAbsoluteIcon(icon) || icon.endsWith(".svg")) return icon;
  return integrations?.apps?.[icon]?.icon || integrations?.tools?.[icon]?.icon || icon;
};

const ZAPIconGroup = ({
  icons = [],
  maxVisible = 2
}) => {
  const safeIcons = (Array.isArray(icons) ? icons : []).filter(Boolean).map(resolveIconFilename);
  const visibleIcons = safeIcons.slice(0, maxVisible);
  const remaining = Math.max(0, safeIcons.length - maxVisible);

  if (safeIcons.length === 0) {
    return <img src={`${plugin_root_url}assets/images/button.svg`} style={{ width: '40px', height: '40px' }} />;
  }

  return <div className="flex border border-[#E5E7EB] rounded-[4px] overflow-hidden bg-white w-fit">
    {visibleIcons.map((icon, index) => {
      const isAbsolute = isAbsoluteIcon(icon);
      const isImage = isAbsolute || icon?.endsWith(".svg");
      const iconSrc = isAbsolute ? icon : `${plugin_root_url}assets/images/icons/${icon}`;
      const isLast = index === visibleIcons.length - 1 && remaining === 0;

      return <div key={index} style={{
        borderRight: isLast ? 'none' : '1px solid #E5E7EB',
        padding: '4px 10px'
      }} className="flex h-[36px] min-w-[40px] justify-center items-center bg-[#F9FAFB]">
        {isImage ? <img src={iconSrc} alt={icon} style={{
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