import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
  API,
  namespace,
  handleSliceSuccess,
  handleSliceError,
} from '@ZAPUtils/helper';


export const exportWorkflows = createAsyncThunk(
  'zaplane/exportWorkflows',
  async (payload = {}, thunkAPI) => {
    try {
      const res = await API.post(
        namespace + 'export',
        payload
      );

      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);


export const importWorkflows = createAsyncThunk(
  'zaplane/importWorkflows',
  async (payload, thunkAPI) => {
    try {
      const res = await API.post(
        namespace + 'import',
        payload,
        {
          headers: {
            'Content-Type': 'application/json',
          },
        }
      );

      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);


export const importWorkflowsFile = createAsyncThunk(
  'zaplane/importWorkflowsFile',
  async (file, thunkAPI) => {
    try {
      const formData = new FormData();
      formData.append('file', file);

      const res = await API.post(
        namespace + 'import',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );

      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

