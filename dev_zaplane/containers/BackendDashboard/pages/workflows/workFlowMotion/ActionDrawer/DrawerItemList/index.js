import { VStack, Button, Box, Flex, Image, Icon } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { __ } from "@wordpress/i18n";
import { FaWordpress } from "react-icons/fa6";
import { secondPrimaryBtn } from "../../../../../../../../assets/scss/chakra/recipe";
import { plugin_root_url } from "@ZAPUtils/helper";
import { ReactComponent as SlackIcon } from "./slack.svg";

const DrawerItemList = ({ list, setSelectedItem, setMode }) => {
  // app list item 
  return (
    <VStack alignItems="left" overflow="hidden"  gap='16px'>
      {list.map(item => (
          <DrawerItemButton
           icon={list?.icon}
            key={item.id}
            item={item}
            onClick={() => setSelectedItem(item)}
          />
      ))}
    </VStack>
  );
}
export default DrawerItemList