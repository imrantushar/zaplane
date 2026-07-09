import DrawerItemButton from "./DrawerItemButton";
import { IoIosArrowForward } from "react-icons/io";
import { FiTool } from "react-icons/fi";
import { ACTION_TOOLS, TRIGGER_TOOLS } from "@ZAPHooks/useActionDrawer/helper";

const DrawerModeList = ({
  setMode,
  setSelectedItem,
  isTrigger,
}) => {
  return <div className="flex flex-col gap-4">
      <DrawerItemButton item={{
      name: "Apps"
    }} onClick={() => setMode("app")} arrowIcon={<IoIosArrowForward />} showType={false} icon={'apps'} />

      {/* Triggers: Tools is a category listing only trigger-capable tools (e.g.
          Schedule). Actions: tools stay as individual buttons for quick access. */}
      {isTrigger
        ? TRIGGER_TOOLS.length > 0 && (
            <DrawerItemButton item={{
              name: "Tools"
            }} onClick={() => setMode("tools")} arrowIcon={<IoIosArrowForward />} showType={false} icon={<FiTool />} />
          )
        : ACTION_TOOLS.map(tool => <DrawerItemButton key={tool.id} item={{
            ...tool,
            type: "tools"
          }} onClick={() => {
            setMode("tools");
            setSelectedItem({
              ...tool,
              mode: "tools"
            });
          }} icon={tool?.icon} />)}
    </div>;
};
export default DrawerModeList;
