import { VStack } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { IoIosArrowForward } from "react-icons/io";

const DrawerModeList=({ setMode, setSelectedItem, isTrigger, source, TOOLS })=> {
  return (
    <VStack spacing={4}>
      <DrawerItemButton
        item={{ name: "Apps" }}
        onClick={() => setMode("app")}
        icon={<IoIosArrowForward />}
        showType={false}
      />

      {(!isTrigger || source === "add") &&
        TOOLS.map(tool => (
          <DrawerItemButton
            key={tool.id}
            item={{ ...tool, type: "tools" }}
            onClick={() => {
              setMode("tools");
              setSelectedItem(tool);
            }}
          />
        ))}
    </VStack>
  );
}
export default DrawerModeList