import { useEffect, useState } from "react";
import {
  Box,
  Button,
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
import SubTopBar from "@ZAPComponents/SubTopBar";
import { primaryBtn } from "../../../../../assets/scss/chakra/recipe";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { getRecipes } from "@ZAPRedux/Slices/recipeSlice/recipeSlice";
import RecipeCard from "./RecipeCard";


const RecipesPage = () => {
  const dispatch = useDispatch();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { recipes } = useSelector(
    (state) => state.recipes || {}
  );

  useEffect(() => {
    dispatch(getRecipes())
  }, [dispatch])
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
      {/* <SubTopBar heading={__("Recipe Library", "zaplane")}>
  
        <Button
          onClick={() => setIsModalOpen(true)}
          {...primaryBtn}
        >
          {__("Create Workflow", "zaplane")}
        </Button>
      </SubTopBar> */}

      <div className="zaplane-page-content">
        <SimpleGrid columns={{ base: 1, md: 2, xl: 3 }} spacing={4} gap='20px'>
          {recipes.map((recipe) => (
            <RecipeCard key={recipe.id} recipe={recipe} />
          ))}
        </SimpleGrid>
      </div>

      <CreateWorkflowModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
      />
    </>
  );
};

export default RecipesPage;