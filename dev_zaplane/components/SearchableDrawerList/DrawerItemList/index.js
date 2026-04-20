import DrawerItemButton from "./DrawerItemButton";
import { __ } from "@wordpress/i18n";
const DrawerItemList = ({
  list,
  setSelectedItem
}) => {
  // app list item 
  return <div overflow="hidden" gap='16px' className="flex flex-col items-left">
      {list.map(item => <DrawerItemButton icon={item?.icon} key={item.id} item={item} onClick={() => setSelectedItem(item)} />)}
    </div>;
};
export default DrawerItemList;