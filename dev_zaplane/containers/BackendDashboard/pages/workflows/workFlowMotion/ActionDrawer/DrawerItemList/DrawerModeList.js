import { VStack } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { IoIosArrowForward } from "react-icons/io";
import { ReactComponent as SlackIcon } from "./slack.svg";

const DrawerModeList = ({ setMode, setSelectedItem, isTrigger, source, TOOLS }) => {
  return (
    <VStack gap="16px">
      <DrawerItemButton
        item={{ name: "Apps" }}
        onClick={() => setMode("app")}
        arrowIcon={<IoIosArrowForward />}
        showType={false}
        icon={SlackIcon}
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
            icon={SlackIcon}
          />
        ))}
    </VStack>
  );
}
export default DrawerModeList