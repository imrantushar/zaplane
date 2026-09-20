import { plugin_root_url } from "@ZAPUtils/helper";
const ZAPIcon = ({
  icon,
  name,
  isAction = false
}) => {
  const isSvg = typeof icon === "string" && icon.endsWith(".svg");
  const isIconKey = typeof icon === "string" && !isSvg;
  const size = isAction ? '40px' : '32px';
  const imgSize = isAction ? '20px' : '18px';

  return <div
    style={{
        width: size,
        height: size,
        background: isAction ? '#FFFFFF' : '#F6F7F8',
        flexShrink: 0,
        borderColor: 'var(--zaplane-border-color)'
    }}
    className="flex justify-center items-center p-2 rounded-[4px] border shadow-sm"
  >
      {isSvg ? (
        <img
          src={icon?.includes("http") ? icon : `${plugin_root_url}assets/images/icons/${icon}`}
          alt={name}
          style={{width: imgSize, height: imgSize, objectFit: 'contain'}}
        />
      ) : isIconKey ? (
        <span className={`zaplane-icon zaplane-icon--${icon} m-0`} style={{fontSize: imgSize}} />
      ) : icon ? (
        <span
          className="flex items-center justify-center text-[var(--zaplane-font-secondary-color)]"
          style={{fontSize: imgSize}}
        >
          {icon}
        </span>
      ) : (
        <span
          className="uppercase font-semibold m-0"
          style={{ fontSize: imgSize, color: 'var(--zaplane-font-secondary-color)' }}
        >
          {(name || '?').charAt(0)}
        </span>
      )}
    </div>;
};
export default ZAPIcon;