import { createSlice } from "@reduxjs/toolkit";
import {
  createRecipeFolder,
  deleteRecipe,
  deleteRecipeFolder,
  getRecipeFolders,
  getRecipes,
  updateRecipe,
  workflowToRecipe,
} from "./actions/recipe";

const recipeSlice = createSlice({
  name: "recipes",
  initialState: {
    folders: [],
    recipes: [],
    loadingFolders: false,
    loadingRecipes: false,
  },
  reducers: {},
  extraReducers: (builder) => {
    builder
      .addCase(getRecipeFolders.pending, (state) => {
        state.loadingFolders = true;
      })
      .addCase(getRecipeFolders.fulfilled, (state, action) => {
        state.loadingFolders = false;
        state.folders = Array.isArray(action.payload) ? action.payload : [];
      })
      .addCase(getRecipeFolders.rejected, (state) => {
        state.loadingFolders = false;
      })
      .addCase(getRecipes.pending, (state) => {
        state.loadingRecipes = true;
      })
      .addCase(getRecipes.fulfilled, (state, action) => {
        state.loadingRecipes = false;
        state.recipes = Array.isArray(action.payload) ? action.payload : [];
      })
      .addCase(getRecipes.rejected, (state) => {
        state.loadingRecipes = false;
      })
      .addCase(updateRecipe.fulfilled, (state, action) => {
        const item = action.payload;
        if (!item?.id) {
          return;
        }
        state.recipes = state.recipes.map((recipe) =>
          Number(recipe.id) === Number(item.id) ? item : recipe
        );
      })
      .addCase(deleteRecipe.fulfilled, (state, action) => {
        const deletedId = action.payload;
        state.recipes = state.recipes.filter((item) => Number(item.id) !== Number(deletedId));
      })
      .addCase(createRecipeFolder.fulfilled, (state) => {
        // Collection is re-fetched from UI after create.
      })
      .addCase(deleteRecipeFolder.fulfilled, (state) => {
        // Collection is re-fetched from UI after delete.
      })
      .addCase(workflowToRecipe.fulfilled, (state, action) => {
        if (action.payload?.id) {
          state.recipes = [action.payload, ...state.recipes];
        }
      });
  },
});

export default recipeSlice.reducer;
