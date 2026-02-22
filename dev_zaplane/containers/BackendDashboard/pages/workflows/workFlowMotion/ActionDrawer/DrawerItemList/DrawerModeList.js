import { VStack } from "@chakra-ui/react";
import DrawerItemButton from "./DrawerItemButton";
import { IoIosArrowForward } from "react-icons/io";
const DefaultIcon = () => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
  >
    <g clipPath="url(#clip0_64_22100)">
      <path
        d="M2.0013 11.3327V13.9993H4.66797V11.3327H2.0013ZM11.3346 11.3327V13.9993H14.0013V11.3327H11.3346ZM6.66797 1.99935V4.66602H9.33464V1.99935H6.66797ZM10.668 4.66602C10.668 5.4024 10.071 5.99935 9.33464 5.99935H8.66797V7.33268H12.0013C12.3549 7.33268 12.694 7.47326 12.944 7.72331C13.1941 7.97336 13.3346 8.3124 13.3346 8.66602V9.99935H14.0013C14.7377 9.99935 15.3346 10.5963 15.3346 11.3327V13.9993C15.3346 14.7357 14.7377 15.3327 14.0013 15.3327H11.3346C10.5983 15.3327 10.0013 14.7357 10.0013 13.9993V11.3327C10.0013 10.5963 10.5983 9.99935 11.3346 9.99935H12.0013V8.66602H4.0013V9.99935H4.66797C5.40435 9.99935 6.0013 10.5963 6.0013 11.3327V13.9993C6.0013 14.7357 5.40435 15.3327 4.66797 15.3327H2.0013C1.26492 15.3327 0.667969 14.7357 0.667969 13.9993V11.3327C0.667969 10.5963 1.26492 9.99935 2.0013 9.99935H2.66797V8.66602C2.66797 8.31239 2.80855 7.97336 3.05859 7.72331C3.30864 7.47326 3.64768 7.33268 4.0013 7.33268H7.33464V5.99935H6.66797C5.93159 5.99935 5.33464 5.4024 5.33464 4.66602V1.99935C5.33464 1.26297 5.93159 0.666016 6.66797 0.666016H9.33464C10.071 0.666016 10.668 1.26297 10.668 1.99935V4.66602Z"
        fill="#738496"
      />
    </g>
    <defs>
      <clipPath id="clip0_64_22100">
        <rect width="16" height="16" fill="white" />
      </clipPath>
    </defs>
  </svg>
);

const DrawerModeList=({ setMode, setSelectedItem, isTrigger, source, TOOLS })=> {
  return (
    <VStack spacing={4}>
      <DrawerItemButton
        item={{ name: "Apps" }}
        onClick={() => setMode("app")}
        icon={<IoIosArrowForward />}
        showType={false}
        toolIcon={DefaultIcon}
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
            // icon={<IoIosArrowForward />}
            toolIcon={DefaultIcon}
          />
        ))}
    </VStack>
  );
}
export default DrawerModeList