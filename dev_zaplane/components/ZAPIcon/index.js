import { plugin_root_url } from "@ZAPUtils/helper";
const ZAPIcon = ({
  icon,
  name,
  isAction = false
}) => {
  const isSvg = icon?.endsWith(".svg");
  const size = isAction ? '40px' : '32px';
  const imgSize = isAction ? '20px' : '16px';
  return <div style={{width: size, height: size, background: isAction ? undefined : '#F6F7F8', flexShrink: 0}} className="flex justify-center items-center p-[7px] rounded-[4px] border">
      {isSvg ? <img src={`${plugin_root_url}assets/images/icons/${icon}`} alt={name} style={{width: imgSize, height: imgSize}} /> : <span className={`zaplane-icon zaplane-icon--${icon} m-0`} />}
    </div>;
};
export default ZAPIcon;