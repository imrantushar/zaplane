import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
    API,
    current_user_can,
    current_user_id,
    is_admin,
    handleSliceSuccess,
    handleSliceError,
    namespace,
    makeRequest,
} from '@ZAPUtils/helper';
import { showNotification } from '../notificationSlice/notificationSlice';

export const topExecutedFlows = createAsyncThunk(
    'zaplane/topExecutedFlows',
    async ( thunkAPI) => {
        try {
            const res = await API.get(
                namespace + `dashboard/top-workflows`
            );
            return res.data.top_workflows;
    
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);
export const deshboardSumary  = createAsyncThunk(
    'zaplane/deshboardSumary',
    async ( thunkAPI) => {
        try {
            const res = await API.get(
                namespace + `dashboard/summary`
            );
            return res.data;
    
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);



const dashboardSlice = createSlice({
    name: 'dashboard',
    initialState: {
        topExecutedFlows:[],
        summary:{},
        isLoading:true

    },
    reducers: {

    },
    extraReducers: (builder) => {
        builder
            .addCase(topExecutedFlows.fulfilled, (state, action) => { 
                state.topExecutedFlows = action.payload;
                state.isLoading =false
            })
            .addCase(deshboardSumary.fulfilled, (state, action) => { 
                state.summary = action.payload;
                state.isLoading =false
            })



    },
});



export default dashboardSlice.reducer;
