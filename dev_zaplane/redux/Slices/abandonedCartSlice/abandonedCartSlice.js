import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';
import { API, namespace, handleSliceError, handleSliceSuccess } from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';

export const fetchCarts = createAsyncThunk(
    'abandonedCart/fetchCarts',
    async (params = {}, thunkAPI) => {
        try {
            const res = await API.get(namespace + 'abandoned-carts', { params });
            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

export const fetchReport = createAsyncThunk(
    'abandonedCart/fetchReport',
    async (params = {}, thunkAPI) => {
        try {
            const res = await API.get(namespace + 'abandoned-carts/report', { params });
            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

export const fetchSettings = createAsyncThunk(
    'abandonedCart/fetchSettings',
    async (_, thunkAPI) => {
        try {
            const res = await API.get(namespace + 'abandoned-cart/settings');
            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

export const saveSettings = createAsyncThunk(
    'abandonedCart/saveSettings',
    async (data, thunkAPI) => {
        try {
            const res = await API.post(namespace + 'abandoned-cart/settings', data);
            thunkAPI.dispatch(showNotification({
                message: __('Settings saved successfully', 'zaplane'),
                isShow: true,
                type: 'success',
            }));
            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

export const deleteCart = createAsyncThunk(
    'abandonedCart/deleteCart',
    async (id, thunkAPI) => {
        try {
            await API.delete(namespace + `abandoned-carts/${id}`);
            thunkAPI.dispatch(showNotification({
                message: __('Cart deleted', 'zaplane'),
                isShow: true,
                type: 'success',
            }));
            return id;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

export const bulkDeleteCarts = createAsyncThunk(
    'abandonedCart/bulkDeleteCarts',
    async (ids, thunkAPI) => {
        try {
            await API.post(namespace + 'abandoned-carts/bulk-delete', { ids });
            thunkAPI.dispatch(showNotification({
                message: __('Carts deleted', 'zaplane'),
                isShow: true,
                type: 'success',
            }));
            return ids;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

const abandonedCartSlice = createSlice({
    name: 'abandonedCart',
    initialState: {
        carts: [],
        pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
        report: null,
        settings: null,
        gemcrm_tags: [],
        gemcrm_lists: [],
        wc_order_statuses: [],
        user_roles: [],
        isLoading: false,
        isSettingsLoading: false,
        isReportLoading: false,
        isSaving: false,
        filters: {
            status: '',
            search: '',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 20,
        },
    },
    reducers: {
        setFilters(state, action) {
            state.filters = { ...state.filters, ...action.payload, page: 1 };
        },
        setPage(state, action) {
            state.filters.page = action.payload;
        },
    },
    extraReducers: (builder) => {
        builder
            .addCase(fetchCarts.pending, (state) => { state.isLoading = true; })
            .addCase(fetchCarts.fulfilled, (state, action) => {
                state.isLoading = false;
                state.carts = action.payload?.data || [];
                state.pagination = action.payload?.pagination || state.pagination;
            })
            .addCase(fetchCarts.rejected, (state) => { state.isLoading = false; })

            .addCase(fetchReport.pending, (state) => { state.isReportLoading = true; })
            .addCase(fetchReport.fulfilled, (state, action) => {
                state.isReportLoading = false;
                state.report = action.payload;
            })
            .addCase(fetchReport.rejected, (state) => { state.isReportLoading = false; })

            .addCase(fetchSettings.pending, (state) => { state.isSettingsLoading = true; })
            .addCase(fetchSettings.fulfilled, (state, action) => {
                state.isSettingsLoading = false;
                state.settings = action.payload?.settings || null;
                state.gemcrm_tags = action.payload?.gemcrm_tags || [];
                state.gemcrm_lists = action.payload?.gemcrm_lists || [];
                state.wc_order_statuses = action.payload?.wc_order_statuses || [];
                state.user_roles = action.payload?.user_roles || [];
            })
            .addCase(fetchSettings.rejected, (state) => { state.isSettingsLoading = false; })

            .addCase(saveSettings.pending, (state) => { state.isSaving = true; })
            .addCase(saveSettings.fulfilled, (state, action) => {
                state.isSaving = false;
                state.settings = action.payload?.settings || state.settings;
            })
            .addCase(saveSettings.rejected, (state) => { state.isSaving = false; })

            .addCase(deleteCart.fulfilled, (state, action) => {
                state.carts = state.carts.filter((c) => c.id !== action.payload);
                state.pagination.total = Math.max(0, state.pagination.total - 1);
            })

            .addCase(bulkDeleteCarts.fulfilled, (state, action) => {
                const deleted = new Set(action.payload);
                state.carts = state.carts.filter((c) => !deleted.has(c.id));
                state.pagination.total = Math.max(0, state.pagination.total - action.payload.length);
            });
    },
});

export const { setFilters, setPage } = abandonedCartSlice.actions;
export default abandonedCartSlice.reducer;
