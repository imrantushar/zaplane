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
      className="w-full flex items-center justify-between p-[4px] rounded-[4px] transition-colors hover:bg-[var(--zaplane-secondary-color)]"
    >
      <div className="flex items-center gap-4">
        <ZAPIcon icon={icon} name={item.name} isAction={true} />
        <span className="zaplane-label">
          {sprintf(__("%s", "zaplane"), item.name)}
        </span>
      </div>
      
      <div className="flex items-center pr-2">
        {arrowIcon && <div className="text-[var(--zaplane-font-secondary-color)]">{arrowIcon}</div>}
        {showType && (
          <span className="zaplane-label">
            {item.type === "tools" ? __('Tool', 'zaplane') : __('App', 'zaplane')}
          </span>
        )}
      </div>
    </button>
  );
};
export default DrawerItemButton;