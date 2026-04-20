import { sprintf, __ } from "@wordpress/i18n";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
const DrawerItemButton = ({
  item,
  onClick,
  showType = true,
  arrowIcon,
  icon
}) => {
  return (
    <button 
      onClick={onClick} 
      className="w-full flex items-center justify-between p-2 rounded-lg transition-colors hover:bg-[var(--zaplane-secondary-color)] group"
    >
      <div className="flex items-center gap-4">
        <ZAPIcon icon={icon} name={item.name} isAction={true} />
        <span className="font-medium text-[var(--zaplane-font-color)] text-[15px]">
          {sprintf(__("%s", "zaplane"), item.name)}
        </span>
      </div>
      
      <div className="flex items-center pr-2">
        {arrowIcon && <div className="text-[var(--zaplane-font-secondary-color)]">{arrowIcon}</div>}
        {showType && item?.type && (
          <span className="text-[13px] text-[var(--zaplane-font-secondary-color)] font-medium">
            {item.type === "tools" ? __('Tool', 'zaplane') : __('App', 'zaplane')}
          </span>
        )}
      </div>
    </button>
  );
};
export default DrawerItemButton;