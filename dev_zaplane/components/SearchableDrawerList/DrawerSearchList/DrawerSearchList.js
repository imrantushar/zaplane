import { sprintf, __ } from "@wordpress/i18n";
import { BookOpen } from "lucide-react";
import ZAPIcon from "@ZAPComponents/ZAPIcon";

const DrawerSearchList = ({
  searchList,
  setMode,
  setSelectedItem,
  setSearch,
  isTrigger
}) => {
  if (!searchList?.length)
    return (
      <span className="zaplane-label text-center block w-full py-4">
        {__("No results found", "zaplane")}
      </span>
    );

  return (
    <div className="flex flex-col gap-1">
      {searchList.map((item) => {
        const docsUrl = isTrigger ? item?.docs_url?.trigger : item?.docs_url?.action;
        return (
        <button
          key={`${item.type}-${item.id}`}
          onClick={() => {
            setMode?.(item.type);
            setSelectedItem(item);
            setSearch("");
          }}
          className="w-full flex items-center justify-between p-[4px] rounded-[4px] transition-colors hover:bg-[var(--zaplane-secondary-color)]"
        >
          <div className="flex items-center gap-4">
            <ZAPIcon icon={item.icon} name={item.name} isAction={true} />
            <span className="zaplane-label font-[400]">
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
            <span className="zaplane-label text-[var(--zaplane-font-secondary-color)]">
              {item.type === "tools"
                ? __("Tool", "zaplane")
                : __("App", "zaplane")}
            </span>
          </div>
        </button>
        );
      })}
    </div>
  );
};

export default DrawerSearchList;