import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import { __ } from "@wordpress/i18n";
import { API, handleSliceError, handleSliceSuccess, namespace } from "@ZAPUtils/helper";

export const getEmailTemplates = createAsyncThunk(
  "zaplane/getEmailTemplates",
  async (args = {}, thunkAPI) => {
    try {
      const params = {};
      if (args.page) params.page = args.page;
      if (args.per_page) params.per_page = args.per_page;
      const res = await API.get(`${namespace}email-templates`, { params });
      return {
        templates: res?.data?.data || [],
        pagination: res?.data?.pagination || null,
      };
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const getEmailTemplate = createAsyncThunk(
  "zaplane/getEmailTemplate",
  async (id, thunkAPI) => {
    try {
      const res = await API.get(`${namespace}email-templates/${id}`);
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const createEmailTemplate = createAsyncThunk(
  "zaplane/createEmailTemplate",
  async (payload = {}, thunkAPI) => {
    try {
      const res = await API.post(`${namespace}email-templates`, payload);
      handleSliceSuccess(thunkAPI, __("Email template created.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const updateEmailTemplate = createAsyncThunk(
  "zaplane/updateEmailTemplate",
  async ({ id, payload }, thunkAPI) => {
    try {
      const res = await API.put(`${namespace}email-templates/${id}`, payload);
      handleSliceSuccess(thunkAPI, __("Email template saved.", "zaplane"));
      return res?.data;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

export const deleteEmailTemplate = createAsyncThunk(
  "zaplane/deleteEmailTemplate",
  async (id, thunkAPI) => {
    try {
      await API.delete(`${namespace}email-templates/${id}`);
      handleSliceSuccess(thunkAPI, __("Email template deleted.", "zaplane"));
      return id;
    } catch (error) {
      return handleSliceError(thunkAPI, error);
    }
  }
);

const emailTemplatesSlice = createSlice({
  name: "emailTemplates",
  initialState: {
    templates: [],
    pagination: null,
    loading: true,
    current: null,
    loadingCurrent: false,
  },
  reducers: {
    clearCurrentTemplate: (state) => {
      state.current = null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(getEmailTemplates.pending, (state) => {
        state.loading = true;
      })
      .addCase(getEmailTemplates.fulfilled, (state, action) => {
        state.loading = false;
        state.templates = Array.isArray(action.payload.templates) ? action.payload.templates : [];
        state.pagination = action.payload.pagination;
      })
      .addCase(getEmailTemplates.rejected, (state) => {
        state.loading = false;
      })
      .addCase(getEmailTemplate.pending, (state) => {
        state.loadingCurrent = true;
        state.current = null;
      })
      .addCase(getEmailTemplate.fulfilled, (state, action) => {
        state.loadingCurrent = false;
        state.current = action.payload;
      })
      .addCase(getEmailTemplate.rejected, (state) => {
        state.loadingCurrent = false;
      })
      .addCase(createEmailTemplate.fulfilled, (state, action) => {
        if (action.payload?.id) state.templates.unshift(action.payload);
      })
      .addCase(updateEmailTemplate.fulfilled, (state, action) => {
        const item = action.payload;
        if (!item?.id) return;
        state.current = item;
        state.templates = state.templates.map((t) =>
          Number(t.id) === Number(item.id) ? item : t
        );
      })
      .addCase(deleteEmailTemplate.fulfilled, (state, action) => {
        const id = action.payload;
        state.templates = state.templates.filter((t) => Number(t.id) !== Number(id));
      });
  },
});

export const { clearCurrentTemplate } = emailTemplatesSlice.actions;
export default emailTemplatesSlice.reducer;
