import { sprintf, __ } from "@wordpress/i18n";
import ZAPIcon from "@ZAPComponents/ZAPIcon";
const DrawerSearchList = ({
  searchList,
  setMode,
  setSelectedItem,
  setSearch
}) => {
  if (!searchList?.length) return <span className="zaplane-label text-center">{__("No results found", "zaplane")}</span>;
  return <div className="flex flex-col gap-2">
      {searchList.map(item => <button key={`${item.type}-${item.id}`} onClick={() => {
      setMode(item.type);
      setSelectedItem(item);
      setSearch("");
    }} height='50px' _hover={{
      bg: "#F6F7F8"
    }} className="justify-between p-[4px] text-var(--zaplane-font-color) bg-var(--zaplane-background)">
          <div gap='12px' className="flex items-center">
              <ZAPIcon icon={item.icon} name={item.name} isAction={true} />
            <span className="zaplane-label font-[400]">{sprintf(__("%s", "zaplane"), item.name)}</span>
          </div>

          <span className="zaplane-label text-[xs]">
            {item.type === 'tools' ? __('Tool', 'zaplane') : __('App', 'zaplane')}
          </span>
        </button>)}
    </div>;
};
export default DrawerSearchList;