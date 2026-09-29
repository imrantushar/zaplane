import { sprintf, __ } from "@wordpress/i18n";
import { BookOpen } from "lucide-react";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
const DrawerItemButton = ({
  item,
  onClick,
  showType = true,
  arrowIcon,
  icon,
  isTrigger
}) => {
  // Picking an app here still shows both a trigger and an action version of
  // the same icon row (trigger list vs action list), so the doc link has to
  // follow which one this row is being rendered for — same trigger/action
  // split as the Select step further in. No URL for that half just hides it.
  const docsUrl = isTrigger ? item?.docs_url?.trigger : item?.docs_url?.action;

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

      <div className="flex items-center gap-3 pr-2">
        {docsUrl && (
          <a
            href={docsUrl}
            target="_blank"
            rel="noopener noreferrer"
            title={__("View docs", "zaplane")}
            onClick={(e) => e.stopPropagation()}
            className="flex items-center text-[var(--zaplane-font-secondary-color)] hover:text-[var(--zaplane-primary-color)]"
          >
            <BookOpen size={14} />
          </a>
        )}
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