import { createAsyncThunk } from "@reduxjs/toolkit";
import { __ } from "@wordpress/i18n";
import { API, handleSliceError, handleSliceSuccess, namespace } from "@ZAPUtils/helper";

export const getRecipeFolders = createAsyncThunk(
  "zaplane/getRecipeFolders",
  async (_, thunkAPI) => {
    try {
      const res = await API.get(`${namespace}recipe-folders`);
      return res?.data?.folders || [];
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const createRecipeFolder = createAsyncThunk(
  "zaplane/createRecipeFolder",
  async (payload, thunkAPI) => {
    try {
      const res = await API.post(`${namespace}recipe-folders`, payload);
      handleSliceSuccess(thunkAPI, __("Folder created successfully.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const updateRecipeFolder = createAsyncThunk(
  "zaplane/updateRecipeFolder",
  async ({ id, payload }, thunkAPI) => {
    try {
      const res = await API.put(`${namespace}recipe-folders/${id}`, payload);
      handleSliceSuccess(thunkAPI, __("Folder updated successfully.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const deleteRecipeFolder = createAsyncThunk(
  "zaplane/deleteRecipeFolder",
  async (id, thunkAPI) => {
    try {
      await API.delete(`${namespace}recipe-folders/${id}`);
      handleSliceSuccess(thunkAPI, __("Folder deleted successfully.", "zaplane"));
      return id;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const getRecipes = createAsyncThunk(
  "zaplane/getRecipes",
  async (args = {}, thunkAPI) => {
    try {
      const query = {};
      if (Object.prototype.hasOwnProperty.call(args, "folder_id")) {
        query.folder_id = args.folder_id;
      }
      const res = await API.get(`${namespace}recipes`, { params: query });
      return res?.data?.recipes || [];
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
  async ({ workflowId, title, description, thumbnail_id, folder_id }, thunkAPI) => {
    try {
      const payload = {
        title,           
        description,        
        thumbnail_id,     
        folder_id 
      };
      const res = await API.post(
        `${namespace}workflows/${workflowId}/to-recipe`,
        payload
      );

      handleSliceSuccess(
        thunkAPI,
        __("Recipe created successfully.", "zaplane")
      );

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
