import { useEffect, useState } from "react";
import {
  Box,
  Flex,
  HStack,
  Icon,
  IconButton,
  Image,
  SimpleGrid,
  Text,
  Button,
  Input,
} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import TopBar from "@ZAPComponents/TopBar";
import { plugin_root_url, route_path } from "@ZAPUtils/helper";
import { FiEye, FiFolder } from "react-icons/fi";
import { IoIosArrowForward } from "react-icons/io";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import {
  deleteRecipeFolder,
  getRecipeFolders,
  getRecipes,
  updateRecipeFolder,
} from "@ZAPRedux/Slices/recipeSlice/actions/recipe";
import { useNavigate } from "react-router-dom";
import ZAPMenu from "@ZAPComponents/ZapMenu";
import WPModal from "@ZAPComponents/Modal/WPModal";

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

const FolderCard = ({ folder }) => {
  const navigate = useNavigate();
  const dispatch = useDispatch();

  const [title, setTitle] = useState(folder?.title || "");
  const [isOpen, setIsOpen] = useState(false);

  const onOpen = () => setIsOpen(true);
  const onClose = () => setIsOpen(false);

  const deletedFolderHandle = () => {
    dispatch(deleteRecipeFolder(folder.id));
  };

  const updateFolderName = async () => {
    if (!title?.trim()) return;

    const payload = {
      title: title,
    };

    await dispatch(
      updateRecipeFolder({
        id: folder?.id,
        payload,
      })
    );

    onClose();
  };

  return (
    <>
      <Box {...cardShell}>
        <Flex justify="space-between" align="flex-start" gap={3} mb={3}>
          <HStack spacing={2} minW={0} align="flex-start">
            <Icon as={FiFolder} boxSize={5} flexShrink={0} mt={0.5} />
            <Text
              fontWeight="600"
              fontSize="15px"
              m={0}
              noOfLines={2}
              cursor="pointer"
              onClick={() =>
                navigate(
                  `${route_path}admin.php?page=zaplane-recipes&action=edit&id=${folder?.id}`
                )
              }
            >
              {folder.title}
            </Text>
          </HStack>

          <ZAPMenu
            isIcon
            items={[
              {
                label: "Edit",
                onClick: onOpen,
              },
              {
                label: "Delete",
                onClick: deletedFolderHandle,
              },
            ]}
          />
        </Flex>

        <Flex justify="space-between" align="flex-end">
          <Text fontSize="13px" color="var(--zaplane-text-muted)">
            {__("0 Workflows", "zaplane")}
          </Text>

          <IconButton
            aria-label={sprintf(
              __("View recipes in %s", "zaplane"),
              folder.title
            )}
            {...miniBtn}
            onClick={() =>
              navigate(
                `${route_path}admin.php?page=zaplane-recipes&action=edit&id=${folder?.id}`
              )
            }
          >
            <Icon as={FiEye} boxSize={4} />
          </IconButton>
        </Flex>
      </Box>

      {/* Modal */}
      <WPModal
        title={__("Rename Folder", "zaplane")}
        isOpen={isOpen}
        onRequestClose={onClose}
        size="large"
      >
        <Input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder="Enter folder name"
          mb={4}
        />

        <Flex justify="flex-end" gap={3}>
          <Button variant="outline" onClick={onClose}>
            {__("Cancel", "zaplane")}
          </Button>

          <Button
            colorScheme="blue"
            onClick={updateFolderName}
            isDisabled={!title.trim()}
          >
            {__("Update", "zaplane")}
          </Button>
        </Flex>
      </WPModal>
    </>
  );
};

const RecipesPage = () => {
  const dispatch = useDispatch();
  const { folders: recipeFolders = [] } = useSelector(
    (state) => state.recipes || {}
  );

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
              type="subtitle"
              fontWeight="medium"
              label={__("Recipe Library", "zaplane")}
            />
          </>
        )}
      />

      <div className="zaplane-page-content">
        <SimpleGrid columns={{ base: 1, sm: 2, lg: 3 }} spacing={4} gap='20px'>
          {recipeFolders?.map((folder) => (
            <FolderCard key={folder.id} folder={folder} />
          ))}
        </SimpleGrid>
      </div>
    </>
  );
};

export default RecipesPage;