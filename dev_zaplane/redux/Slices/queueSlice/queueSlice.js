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


export const getQueueList = createAsyncThunk(
  'zaplane/getQueueList',
  async (_, thunkAPI) => {
    try {
      const res = await API.get(
        namespace + 'queue'
      );

      handleSliceSuccess(
        thunkAPI,
        __('Queue fetched successfully', 'workflow')
      );

      return res.data; 
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);
const queueSlice = createSlice({
	name: 'queue',
	initialState: {
		data: [],

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
			.addCase(getQueueList.fulfilled, (state, action) => {
				state.data = action.payload;
			})
			



	},
});



export default queueSlice.reducer;
