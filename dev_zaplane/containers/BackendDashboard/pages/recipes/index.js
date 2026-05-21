import { useEffect, useState } from "react";

import { __ } from "@wordpress/i18n";
import { useDispatch, useSelector } from "react-redux";
import CreateWorkflowModal from "@ZAPComponents/CreateWorkflowModal";
import { getRecipes } from "@ZAPRedux/Slices/recipeSlice/recipeSlice";
import RecipeCard from "./RecipeCard";
import CustomTableMessage from "@ZAPComponents/Oops/CustomTableMessage";
import './styles.scss';
import RecipesSkeleton from "@ZAPComponents/ZaplaneLoader/RecipesSkeletion";
import PageLayout from "@ZAPComponents/PageLayout";
import Search from "@ZAPComponents/Search";
const RecipesPage = () => {
  const dispatch = useDispatch();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");
  const {
    recipes,
    loadingRecipes
  } = useSelector(state => state.recipes || {});
  useEffect(() => {
    dispatch(getRecipes());
  }, [dispatch]);
  const filteredRecipes = searchTerm
    ? (recipes || []).filter(r => (r.name || r.title || "").toLowerCase().includes(searchTerm.toLowerCase()))
    : (recipes || []);
  return <PageLayout title="Recipes" isLoading={loadingRecipes} skeleton={RecipesSkeleton}>
        <div className="mb-4">
          <Search placeholder={__("Search recipes...", "zaplane")} onSearchHandler={setSearchTerm} />
        </div>
        {filteredRecipes?.length ? <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 items-start">
            {filteredRecipes.map(recipe => <RecipeCard key={recipe.id} recipe={recipe} />)}
          </div> : <div>
              <CustomTableMessage title={__('No Data Available!!!', 'zaplane')} subText={'Please, create data to see the available list here.'} />
            </div>}

        <CreateWorkflowModal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} />
    </PageLayout>;
};
export default RecipesPage;
