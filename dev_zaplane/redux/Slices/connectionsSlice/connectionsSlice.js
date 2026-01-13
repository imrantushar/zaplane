import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';
import { API, handleSliceSuccess, handleSliceError, namespace } from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';


export const fetchConnections = createAsyncThunk(
  'connections/fetchConnections',
  async (_, thunkAPI) => {
    try {
      const res = await API.get(namespace + 'connections');
	  console.log(res,'resssssssssssss')
      return res.data.connections || [];
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
  async ({ app, name, credentials }, thunkAPI) => {
    try {
      const res = await API.post(namespace + 'connections/oauth/init', {
        app,
        name,
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
  async ({ app, name, authType, credentials }, thunkAPI) => {
    try {
      const res = await API.post(namespace + 'connections', {
        app,
        name,
        auth_type: authType,
        credentials,
      });

      handleSliceSuccess(
        thunkAPI,
        res.data?.test_result?.message || __('Connection created', 'workflow')
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
        res.data?.message || __('Connection successful', 'workflow')
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
          message: __('Connection deleted', 'workflow'),
        })
      );
      return connectionId;
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);
const connectionsSlice = createSlice({
  name: 'connections',
  initialState: {
    list: [],
    authFields: {},
    oauthData: null,
    loading: false,
    error: null,
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
      .addCase(fetchConnections.pending, (state) => {
        state.loading = true;
        state.error = null;
      })
      .addCase(fetchConnections.fulfilled, (state, action) => {
        state.loading = false;
        state.list = action.payload;
      })
      .addCase(fetchAuthFields.fulfilled, (state, action) => {
        state.authFields = action.payload || {};
      })

      .addCase(initOAuth.fulfilled, (state, action) => {
        state.oauthData = action.payload;
      })

      .addCase(createTokenConnection.fulfilled, (state, action) => {
        state.list.push(action.payload);
      })
      .addCase(testConnection.fulfilled, (state, action) => {
        const index = state.list.findIndex(c => c.id === action.payload.id);
        if (index !== -1) {
          state.list[index].last_tested_at = new Date().toISOString();
          state.list[index].last_test_status = action.payload.result.success ? 'success' : 'failed';
        }
      })
      .addCase(deleteConnection.fulfilled, (state, action) => {
        state.list = state.list.filter(c => c.id !== action.payload);
      });
  },
});

export const { resetAuthFields, resetOAuth } = connectionsSlice.actions;
export default connectionsSlice.reducer;
