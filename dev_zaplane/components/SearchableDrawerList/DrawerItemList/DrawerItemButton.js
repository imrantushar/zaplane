import { sprintf, __ } from "@wordpress/i18n";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
const DrawerItemButton = ({
  item,
  onClick,
  showType = true,
  arrowIcon,
  icon
}) => {
  return <button height='50px' _hover={{
    bg: "#F6F7F8"
  }} onClick={onClick} className="w-[full] p-[4px] rounded-[4px] bg-var(--zaplane-background) text-var(--zaplane-font-color) justify-between">
      <div gap="8px" className="flex">
        <ZAPIcon icon={icon} name={item.name} isAction={true} />
        <span as="span" className="zaplane-label font-[400] flex items-center">{sprintf(__("%s", "zaplane"), item.name)}</span>
      </div>
      {arrowIcon && arrowIcon}
      {showType && item.type && <span className="zaplane-label text-[xs]">
          {item.type === "tools" ? __('Tool', 'zaplane') : __('App', 'zaplane')}
        </span>}
    </button>;
};
export default DrawerItemButton;