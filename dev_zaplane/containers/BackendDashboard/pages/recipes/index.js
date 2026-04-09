import { useEffect, useState } from "react";
import {
  Box,
  Flex,
  HStack,
  Icon,
  IconButton,
  Image,
  Menu,
  Portal,
  SimpleGrid,
  Text,
} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { useDispatch } from "react-redux";
import TopBar from "@ZAPComponents/TopBar";
import { plugin_root_url } from "@ZAPUtils/helper";
import { FiEye, FiFolder } from "react-icons/fi";
import { BsThreeDotsVertical } from "react-icons/bs";
import { IoIosArrowForward } from "react-icons/io";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import { getRecipeFolders, getRecipes } from "@ZAPRedux/Slices/recipeSlice/actions/recipe";

/** Static folder cards (matches design reference). */
const STATIC_FOLDER_CARDS = [
  { id: "static-1", name: "dfdf", recipeCount: 0 },
  { id: "static-2", name: "hhh", recipeCount: 1 },
  { id: "static-3", name: "hiu", recipeCount: 0 },
  { id: "static-4", name: "jhhh", recipeCount: 0 },
  { id: "static-5", name: "mixan", recipeCount: 0 },
];

const cardShell = {
  bg: "var(--zaplane-background)",
  borderWidth: "1px",
  borderColor: "var(--zaplane-border-color)",
  borderRadius: "12px",
  p: 4,
  position: "relative",
  minH: "104px",
};

const miniBtn = {
  size: "sm",
  variant: "outline",
  borderColor: "var(--zaplane-border-color)",
  borderRadius: "6px",
};

const FolderCard = ({ folder, onView }) => {
  const countLabel =
    folder.recipeCount === 1
      ? __("1 Recipe", "zaplane")
      : sprintf(__("%d Recipes", "zaplane"), folder.recipeCount);

  return (
    <Box {...cardShell}>
      <Flex justify="space-between" align="flex-start" gap={3} mb={3}>
        <HStack spacing={2} minW={0} align="flex-start">
          <Icon as={FiFolder} boxSize={5}  flexShrink={0} mt={0.5} />
          <Text fontWeight="600" fontSize="15px" lineHeight="1.3" noOfLines={2} m={0}>
            {folder.name}
          </Text>
        </HStack>
        <Menu.Root>
          <Menu.Trigger asChild>
            <IconButton aria-label={__("Folder options", "zaplane")} {...miniBtn} flexShrink={0}>
              <Icon as={BsThreeDotsVertical} boxSize={4} />
            </IconButton>
          </Menu.Trigger>
          <Portal>
            <Menu.Positioner>
              <Menu.Content minW="160px">
                <Menu.Item value="view" onClick={() => onView(folder)}>
                  {__("View", "zaplane")}
                </Menu.Item>
                <Menu.Item
                  value="rename"
                  onClick={() =>
                    window.alert(
                      sprintf(
                        /* translators: folder name */
                        __("Rename “%s” (static demo).", "zaplane"),
                        folder.name
                      )
                    )
                  }
                >
                  {__("Rename", "zaplane")}
                </Menu.Item>
                <Menu.Item
                  value="delete"
                  onClick={() =>
                    window.alert(
                      sprintf(
                        /* translators: folder name */
                        __("Delete “%s” (static demo).", "zaplane"),
                        folder.name
                      )
                    )
                  }
                >
                  {__("Delete folder", "zaplane")}
                </Menu.Item>
              </Menu.Content>
            </Menu.Positioner>
          </Portal>
        </Menu.Root>
      </Flex>

      <Flex justify="space-between" align="flex-end">
        <Text fontSize="13px" color="var(--zaplane-text-muted)" m={0}>
          {countLabel}
        </Text>
        <IconButton
          aria-label={sprintf(__("View recipes in %s", "zaplane"), folder.name)}
          {...miniBtn}
          onClick={() => onView(folder)}
        >
          <Icon as={FiEye} boxSize={4} />
        </IconButton>
      </Flex>
    </Box>
  );
};

const RecipesPage = () => {
  const dispatch = useDispatch();
  const [activeFolder, setActiveFolder] = useState(null);

  useEffect(() => {
    dispatch(getRecipeFolders());
    dispatch(getRecipes());
  }, [dispatch]);

  return (
    <>
      <TopBar
        leftContent={() => (
          <>
            <Flex
              height="40px"
              width="40px"
              borderRadius="20px"
              gap="10px"
              background="var(--zaplane-second-primary)"
              alignItems="center"
              justifyContent="center"
            >
              <Image
                src={`${plugin_root_url}assets/images/zaplane.svg`}
                boxSize="20px"
              />
            </Flex>
            <IoIosArrowForward />
            <ZAPLabel
              as="h2"
              color="var(--zapplane-font-color)"
              type="subtitle"
              fontWeight="medium"
              label={__("Recipe Library", "zaplane")}
            />
          </>
        )}
      />

      <div className="zaplane-page-content">
     
        <SimpleGrid columns={{ base: 1, sm: 2, lg: 3 }} spacing={4} gap='16px'>
          {STATIC_FOLDER_CARDS.map((folder) => (
            <FolderCard
              key={folder.id}
              folder={folder}
              onView={(f) => setActiveFolder(f)}
            />
          ))}
        </SimpleGrid>
      </div>
    </>
  );
};

export default RecipesPage;
