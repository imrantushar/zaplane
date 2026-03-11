import { VStack } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { __ } from "@wordpress/i18n";


const DrawerItemList = ({ list, setSelectedItem, setMode }) => {
  // app list item 
  return (
    <VStack alignItems="left" overflow="hidden"  gap='16px'>
      {list.map(item => (
          <DrawerItemButton
           icon={item?.icon}
            key={item.id}
            item={item}
            onClick={() => setSelectedItem(item)}
          />
      ))}
    </VStack>
  );
}
export default DrawerItemList