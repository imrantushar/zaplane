import DrawerItemButton from "./DrawerItemButton";
import { __ } from "@wordpress/i18n";
const DrawerItemList = ({
  list,
  setSelectedItem,
  isTrigger
}) => {
  // app list item
  return <div  className="flex flex-col items-center gap-[16px]">
      {list.map(item => <DrawerItemButton icon={item?.icon} key={item.id} item={item} isTrigger={isTrigger} onClick={() => setSelectedItem(item)} />)}
    </div>;
};
export default DrawerItemList;