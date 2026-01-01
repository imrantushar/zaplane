import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import apiFetch from '@wordpress/api-fetch';

// --- 1. Fetch Logs ---
export const fetchLogs = createAsyncThunk(
    'logs/fetchLogs',
    async ({ page, per_page, search = '' }, { rejectWithValue }) => {
        try {
            const path = `/gamify/v1/logs?page=${page}&per_page=${per_page}&search=${search}`;
            const response = await apiFetch({ path, parse: false });
            const total = response.headers.get('X-WP-Total');
            const data = await response.json();
            return { data, total: parseInt(total || 0, 10) };
        } catch (error) {
            return rejectWithValue(error.message);
        }
    }
);

// --- 2. Manual Action Trigger (Create) ---
export const manualLogAction = createAsyncThunk(
    'logs/manualAction',
    async (formData, { rejectWithValue, dispatch }) => {
        try {
            const response = await apiFetch({
                path: '/gamify/v1/actions/manual',
                method: 'POST',
                data: formData
            });
            dispatch(fetchLogs({ page: 1, per_page: 10 }));
            return response;
        } catch (error) {
            return rejectWithValue(error.message);
        }
    }
);

// --- 3. Update Log Action (NEW) ---
export const updateLogAction = createAsyncThunk(
    'logs/updateLog',
    async ({ id, data }, { rejectWithValue, dispatch }) => {
        try {
            const response = await apiFetch({
                path: `/gamify/v1/logs/${id}`,
                method: 'PUT', // or PATCH
                data: data
            });

            // Refresh logs to reflect changes
            dispatch(fetchLogs({ page: 1, per_page: 10 }));

            return response;
        } catch (error) {
            return rejectWithValue(error.message);
        }
    }
);

const logsSlice = createSlice({
    name: 'logs',
    initialState: {
        items: [],
        totalItems: 0,
        currentPage: 1,
        rowsPerPage: 10,
        searchQuery: '',
        status: 'idle',
        actionStatus: 'idle',
        error: null,
    },
    reducers: {
        setPage: (state, action) => { state.currentPage = action.payload; },
        setRowsPerPage: (state, action) => { state.rowsPerPage = action.payload; },
        setSearchQuery: (state, action) => { state.searchQuery = action.payload; },
        resetActionStatus: (state) => { state.actionStatus = 'idle'; }
    },
    extraReducers: (builder) => {
        builder
            // Fetch Logs
            .addCase(fetchLogs.pending, (state) => { state.status = 'loading'; })
            .addCase(fetchLogs.fulfilled, (state, action) => {
                state.status = 'succeeded';
                state.items = action.payload.data;
                state.totalItems = action.payload.total;
            })
            .addCase(fetchLogs.rejected, (state, action) => {
                state.status = 'failed';
                state.error = action.payload;
            })

            // Manual & Update Actions (Share same loading logic)
            .addCase(manualLogAction.pending, (state) => { state.actionStatus = 'loading'; })
            .addCase(manualLogAction.fulfilled, (state) => { state.actionStatus = 'succeeded'; })
            .addCase(manualLogAction.rejected, (state, action) => {
                state.actionStatus = 'failed';
                state.error = action.payload;
            })

            .addCase(updateLogAction.pending, (state) => { state.actionStatus = 'loading'; })
            .addCase(updateLogAction.fulfilled, (state) => { state.actionStatus = 'succeeded'; })
            .addCase(updateLogAction.rejected, (state, action) => {
                state.actionStatus = 'failed';
                state.error = action.payload;
            });
    },
});

export const { setPage, setRowsPerPage, setSearchQuery, resetActionStatus } = logsSlice.actions;
export default logsSlice.reducer;