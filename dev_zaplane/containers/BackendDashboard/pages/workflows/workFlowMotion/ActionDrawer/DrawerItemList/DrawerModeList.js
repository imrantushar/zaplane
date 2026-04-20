import DrawerItemButton from "./DrawerItemButton";
import { IoIosArrowForward } from "react-icons/io";
const DrawerModeList = ({
  setMode,
  setSelectedItem,
  isTrigger,
  source,
  TOOLS
}) => {
  return <div gap="16px" className="flex flex-col">
      <DrawerItemButton item={{
      name: "Apps"
    }} onClick={() => setMode("app")} arrowIcon={<IoIosArrowForward />} showType={false} icon={'apps'} />

      {(!isTrigger || source === "add") && TOOLS.map(tool => <DrawerItemButton key={tool.id} item={{
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