import { sprintf, __ } from "@wordpress/i18n";
import ZAPIcon from "@ZAPComponents/ZAPIcon";

const DrawerSearchList = ({
  searchList,
  setMode,
  setSelectedItem,
  setSearch
}) => {
  if (!searchList?.length)
    return (
      <span className="zaplane-label text-center block w-full py-4">
        {__("No results found", "zaplane")}
      </span>
    );

  return (
    <div className="flex flex-col gap-1">
      {searchList.map((item) => (
        <button
          key={`${item.type}-${item.id}`}
          onClick={() => {
            setMode(item.type);
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

          <div className="flex items-center pr-2">
            <span className="zaplane-label text-[var(--zaplane-font-secondary-color)]">
              {item.type === "tools"
                ? __("Tool", "zaplane")
                : __("App", "zaplane")}
            </span>
          </div>
        </button>
      ))}
    </div>
  );
};

export default DrawerSearchList;