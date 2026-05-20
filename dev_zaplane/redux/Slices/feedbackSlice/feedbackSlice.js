import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { API, namespace, handleSliceError } from '@ZAPUtils/helper';

export const fetchFeedback = createAsyncThunk(
    'feedback/fetchFeedback',
    async (params = {}, thunkAPI) => {
        try {
            const res = await API.get(namespace + 'feedback', { params });
            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

export const fetchFeedbackSummary = createAsyncThunk(
    'feedback/fetchSummary',
    async (_, thunkAPI) => {
        try {
            const res = await API.get(namespace + 'feedback/summary');
            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

const feedbackSlice = createSlice({
    name: 'feedback',
    initialState: {
        items: [],
        summary: null,
        pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
        isLoading: false,
        isSummaryLoading: false,
        filters: { rating: 0, page: 1, per_page: 20 },
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
            .addCase(fetchFeedback.pending, (state) => { state.isLoading = true; })
            .addCase(fetchFeedback.fulfilled, (state, action) => {
                state.isLoading = false;
                state.items = action.payload?.data || [];
                state.pagination = action.payload?.pagination || state.pagination;
            })
            .addCase(fetchFeedback.rejected, (state) => { state.isLoading = false; })

            .addCase(fetchFeedbackSummary.pending, (state) => { state.isSummaryLoading = true; })
            .addCase(fetchFeedbackSummary.fulfilled, (state, action) => {
                state.isSummaryLoading = false;
                state.summary = action.payload;
            })
            .addCase(fetchFeedbackSummary.rejected, (state) => { state.isSummaryLoading = false; });
    },
});

export const { setFilters, setPage } = feedbackSlice.actions;
export default feedbackSlice.reducer;
