import { useEffect, useState } from "react";
import {
  Box,
  SimpleGrid,

} from "@chakra-ui/react";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { getRecipes } from "@ZAPRedux/Slices/recipeSlice/recipeSlice";
import RecipeCard from "./RecipeCard";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";
import './styles.scss'
import RecipesSkeleton from "@ZAPComponents/ZaplaneLoader/RecipesSkeletion";
import PageLayout from "@ZAPComponents/PageLayout";


const RecipesPage = () => {
  const dispatch = useDispatch();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { recipes, loadingRecipes } = useSelector(
    (state) => state.recipes || {}
  );

  useEffect(() => {
    dispatch(getRecipes())
  }, [dispatch])
    return (
        <PageLayout
            title="Recipes"
            isLoading={loadingRecipes}
            skeleton={RecipesSkeleton}
        >
        {
          recipes?.length ? <SimpleGrid columns={{ base: 1, md: 2, xl: 3 }} spacing={4} gap='20px' flexWrap="wrap"
            alignItems="flex-start">
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

        <CreateWorkflowModal
            isOpen={isModalOpen}
            onClose={() => setIsModalOpen(false)}
        />
    </PageLayout>
  );
};

export default RecipesPage;