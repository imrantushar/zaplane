import { plugin_root_url } from "@ZAPUtils/helper";
const ZAPIcon = ({
  icon,
  name,
  isAction = false
}) => {
  const isSvg = icon?.endsWith(".svg");
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
      ) : (
        <span className={`zaplane-icon zaplane-icon--${icon} m-0`} style={{fontSize: imgSize}} />
      )}
    </div>;
};
export default ZAPIcon;