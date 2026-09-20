
import { createSlice } from "@reduxjs/toolkit";
import { createAsyncThunk } from "@reduxjs/toolkit";
import { __ } from "@wordpress/i18n";
import { API, handleSliceError, handleSliceSuccess, namespace } from "@ZAPUtils/helper";
 
export const getRecipes = createAsyncThunk(
  "zaplane/getRecipes",
  async (args = {}, thunkAPI) => {
    try {
      // The page searches and filters what it has, so it asks for every recipe
      // the API will give at once rather than the first page of 20.
      const query = { per_page: args.per_page || 100 };
      if (args.page) query.page = args.page;
      if (args.type) query.type = args.type;
 
      const res = await API.get(`${namespace}recipes`, { params: query });
      return {
        recipes: res?.data?.data || [],
        pagination: res?.data?.pagination || null,
      };
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);
 
export const getRecipe = createAsyncThunk(
  "zaplane/getRecipe",
  async (id, thunkAPI) => {
    try {
      const res = await API.get(`${namespace}recipes/${id}`);
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);
 
export const updateRecipe = createAsyncThunk(
  "zaplane/updateRecipe",
  async ({ id, payload }, thunkAPI) => {
    try {
      const res = await API.put(`${namespace}recipes/${id}`, payload);
      handleSliceSuccess(thunkAPI, __("Recipe updated successfully.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);
 
export const deleteRecipe = createAsyncThunk(
  "zaplane/deleteRecipe",
  async (id, thunkAPI) => {
    try {
      await API.delete(`${namespace}recipes/${id}`);
      handleSliceSuccess(thunkAPI, __("Recipe deleted successfully.", "zaplane"));
      return id;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);
 
export const workflowToRecipe = createAsyncThunk(
  "zaplane/workflowToRecipe",
  async ({ workflowId, title, description, thumbnail_id }, thunkAPI) => {
    try {
      const payload = { title, description, thumbnail_id };
      const res = await API.post(
        `${namespace}workflows/${workflowId}/to-recipe`,
        payload
      );
      handleSliceSuccess(thunkAPI, __("Recipe created successfully.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);
 
export const recipeToWorkflow = createAsyncThunk(
  "zaplane/recipeToWorkflow",
  async ({ recipeId, payload }, thunkAPI) => {
    try {
      const res = await API.post(`${namespace}recipes/${recipeId}/to-workflow`, payload);
      handleSliceSuccess(thunkAPI, __("Workflow created successfully.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);
 
const recipeSlice = createSlice({
  name: "recipes",
  initialState: {
    recipes: [],
    pagination: null,
    loadingRecipes: true,
  },
  reducers: {},
  extraReducers: (builder) => {
    builder
 
    
      .addCase(getRecipes.fulfilled, (state, action) => {
        state.loadingRecipes = false;
        state.recipes = Array.isArray(action.payload.recipes) ? action.payload.recipes : [];
        state.pagination = action.payload.pagination;
      })
    
 
      .addCase(updateRecipe.fulfilled, (state, action) => {
        const item = action.payload;
        if (!item?.id) return;
        state.recipes = state.recipes.map((recipe) =>
          Number(recipe.id) === Number(item.id) ? item : recipe
        );
      })
 
      .addCase(deleteRecipe.fulfilled, (state, action) => {
        const deletedId = action.payload;
        state.recipes = state.recipes.filter(
          (item) => Number(item.id) !== Number(deletedId)
        );
      })
 
      .addCase(workflowToRecipe.fulfilled, (state, action) => {
        if (action.payload?.id) {
          state.recipes = [action.payload, ...state.recipes];
        }
      });
  },
});
 
export default recipeSlice.reducer;