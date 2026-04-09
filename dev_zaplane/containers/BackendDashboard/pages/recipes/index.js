import { useEffect } from "react";
import {
  Box,
  Flex,

  Image,
  SimpleGrid,

} from "@chakra-ui/react";
import { __, sprintf } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import TopBar from "@ZAPComponents/TopBar";
import { plugin_root_url } from "@ZAPUtils/helper";
import { IoIosArrowForward } from "react-icons/io";
import ZAPLabel from "@ZAPComponents/Labels/ZAPLabel";
import {
  getRecipeFolders,
} from "@ZAPRedux/Slices/recipeSlice/actions/recipe";
import FolderCard from "./FolderCard";

const RecipesPage = () => {
  const dispatch = useDispatch();
  const { folders: recipeFolders = [] } = useSelector(
    (state) => state.recipes || {}
  );

  useEffect(() => {
    dispatch(getRecipeFolders());
  }, [dispatch]);
  console.log(recipeFolders,'recipeFolders');

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