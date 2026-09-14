import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';
import { API, handleSliceSuccess, handleSliceError, namespace } from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';


export const fetchConnections = createAsyncThunk(
  'connections/fetchConnections',
  async ({ app, page = 1, per_page = 20 } = {}, thunkAPI) => {
    try {
      const res = await API.get(namespace + 'connections', {
        params: { app, page, per_page },
      });

      const { data, pagination } = res.data;

      return {
        data: data || [],
        currentPage: pagination.page ,
        itemPerPage: pagination.per_page ,
        totalItems: pagination.total ,
        totalPages: pagination.total_pages ,
      };
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

// Fetch auth fields for an integration
export const fetchAuthFields = createAsyncThunk(
  'connections/fetchAuthFields',
  async ({ app, authType }, thunkAPI) => {
    try {
      let url = namespace + `connections/auth-fields/${app}`;
      if (authType) url += `?auth_type=${authType}`;
      const res = await API.get(url);
      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

// Initialize OAuth flow
export const initOAuth = createAsyncThunk(
  'connections/initOAuth',
  async ({ app, name, icon, credentials }, thunkAPI) => {
    try {
      const res = await API.post(namespace + 'connections/oauth/init', {
        app,
        name,
        icon,
        credentials,
      });
      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

// Create token-based connection
export const createTokenConnection = createAsyncThunk(
  'connections/createTokenConnection',
  async ({ app, name,icon, authType, credentials }, thunkAPI) => {
    try {
      const res = await API.post(namespace + 'connections', {
        app,
        name,
        icon,
        auth_type: authType,
        credentials,
      });

      handleSliceSuccess(
        thunkAPI,
        res.data?.test_result?.message || __('Connection created', 'zaplane')
      );

      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

// Test connection
export const testConnection = createAsyncThunk(
  'connections/testConnection',
  async (connectionId, thunkAPI) => {
    try {
      const res = await API.post(namespace + `connections/${connectionId}/test`);

      handleSliceSuccess(
        thunkAPI,
        res.data?.message || __('Connection successful', 'zaplane')
      );

      return {
        id: connectionId,
        result: res.data,
      };
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

// Delete connection
export const deleteConnection = createAsyncThunk(
  'connections/deleteConnection',
  async (connectionId, thunkAPI) => {
    try {
      await API.delete(namespace + `connections/${connectionId}`);
      thunkAPI.dispatch(
        showNotification({
          type: 'success',
          message: __('Connection deleted', 'zaplane'),
        })
      );
      return connectionId;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);
// Get single connection
export const fetchSingleConnection = createAsyncThunk(
  'connections/fetchSingleConnection',
  async (id, thunkAPI) => {
    try {
      const res = await API.get(namespace + `connections/${id}`);
      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

// Update connection
export const updateConnection = createAsyncThunk(
  'connections/updateConnection',
  async ({ id, payload }, thunkAPI) => {
    try {
      const res = await API.put(
        namespace + `connections/${id}`,
        payload
      );

      handleSliceSuccess(
        thunkAPI,
        __('Connection updated successfully', 'zaplane')
      );

      return res.data;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);
const connectionsSlice = createSlice({
  name: 'connections',
  initialState: {
    allConnection: [],
    authFields: {},
    oauthData: null,
    loading: true,
    error: null,
    connection: {},
    itemPerPage: 10,
    currentPage: 1,
    totalItems: 0,
    totalPages: 0,
  },
  reducers: {
    resetAuthFields: (state) => {
      state.authFields = {};
    },
    resetOAuth: (state) => {
      state.oauthData = null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchConnections.fulfilled, (state, action) => {
       const { data, currentPage, itemPerPage, totalItems, totalPages } = action.payload;
        state.allConnection = data;
        state.currentPage = currentPage;
        state.itemPerPage = itemPerPage;
        state.totalItems = totalItems;
        state.totalPages = totalPages;
        state.loading = false;
      })
      .addCase(fetchAuthFields.fulfilled, (state, action) => {
        state.authFields = action.payload || {};
        state.loading =false
      })

      .addCase(initOAuth.fulfilled, (state, action) => {
        state.oauthData = action.payload;
      })

      .addCase(createTokenConnection.fulfilled, (state, action) => {
        state.allConnection.push(action.payload);
      })
      .addCase(testConnection.fulfilled, (state, action) => {
        const index = state.allConnection.findIndex(c => c.id === action.payload.id);
        if (index !== -1) {
          state.allConnection[index].last_tested_at = new Date().toISOString();
          state.allConnection[index].last_test_status = action.payload.result.success ? 'success' : 'failed';
        }
      })
      .addCase(deleteConnection.fulfilled, (state, action) => {
        state.allConnection = state.allConnection.filter(c => c.id !== action.payload);
      })
      .addCase(fetchSingleConnection.fulfilled, (state, action) => {
        state.loading = false;
        state.connection = action.payload;
      })
      .addCase(updateConnection.fulfilled, (state, action) => {
        const index = state.allConnection.findIndex(
          (c) => c.id === action.payload.id
        );
        if (index !== -1) {
          state.allConnection[index] = action.payload;
        }
        if (state.connection?.id === action.payload.id) {
          state.connection = action.payload;
        }
      })
  },
});

export const { resetAuthFields, resetOAuth } = connectionsSlice.actions;
export default connectionsSlice.reducer;
