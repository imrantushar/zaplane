import { useEffect, useState } from "react";
import {
  Box,
  Flex,
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
import ZAPLoading from "@ZAPComponents/Loading";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";
import './styles.scss'


const RecipesPage = () => {
  const dispatch = useDispatch();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { recipes, loadingRecipes } = useSelector(
    (state) => state.recipes || {}
  );

  useEffect(() => {
    dispatch(getRecipes())
  }, [dispatch])
  if (loadingRecipes) return <ZAPLoading />
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
              <img
                src={`${plugin_root_url}assets/images/zaplane.svg`}
              />
            </Flex>

            <IoIosArrowForward />

            <ZAPLabel
              as="h2"
              type="subtitle"
              fontWeight="medium"
              label={__("Recipe", "zaplane")}
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
      <SubTopBar heading={__("Recipes", "zaplane")}>

      </SubTopBar>

      <div className="zaplane-page-content">
        {
          recipes?.length ? <SimpleGrid columns={{ base: 1, md: 2, xl: 3 }} spacing={4} gap='20px'>
            {recipes.map((recipe) => (
              <RecipeCard key={recipe.id} recipe={recipe} />
            ))}
          </SimpleGrid> : (
           <Box className="zaplane-recipes-custom-msg">
             <CustomTableMessage
                  title={__(
                    'No Data Available!!!',
                    'zaplane'
                  )}
                  subText={'Please, create data to see the available list here.'}
                />
           </Box>
          )
        }

      </div>

      <CreateWorkflowModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
      />
    </>
  );
};

export default RecipesPage;