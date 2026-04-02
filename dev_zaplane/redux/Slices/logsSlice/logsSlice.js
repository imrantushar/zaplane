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

export const getSingleRunDetails = createAsyncThunk(
	'zaplane/getSingleRun',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}`
			);

			handleSliceSuccess(thunkAPI, __('Run details fetched successfully', 'workflow'));

			return res.data;
			// {
			//   run: {},
			//   nodes: [],
			//   edges: [],
			//   logs: []
			// }
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getRunsList = createAsyncThunk(
	'zaplane/getRunsList',
	async ({ page = 1, per_page = 20 } = {}, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs`,{
					params: { page, per_page },
				}
			);
			const { runs = [], pagination = {} } = res.data;
			return {
				data: runs,
				currentPage: pagination.page || 1,
				itemPerPage: pagination.per_page || 20,
				totalItems: pagination.total || 0,
				totalPages: pagination.total_pages || 0,
			}; 
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const retryNodeRun = createAsyncThunk(
  'zaplane/retryNodeRun',
  async (nodeRunId, thunkAPI) => {
    try {
      const res = await API.post(
        namespace + `node-runs/${parseInt(nodeRunId)}/retry`,
        {}
      );

      thunkAPI.dispatch(
        showNotification({
          message: __('Node retried and queued successfully', 'workflow'),
          isShow: true,
          type: 'success',
        })
      );

      return {
        nodeRunId,
        status: res?.data?.status || 'queued',
      };
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);


const logSlice = createSlice({
	name: 'logs',
	initialState: {
		data: [],
		isLoading:true,
		itemPerPage: 10,
		currentPage: 1,
		totalItems: 0,

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
			.addCase(getRunsList.fulfilled, (state, action) => {
				const { data, currentPage, itemPerPage, totalItems, totalPages } = action.payload;
				state.data = data || [];
				state.currentPage = currentPage;
				state.itemPerPage = itemPerPage;
				state.totalItems = totalItems;
				state.totalPages = totalPages;
				state.isLoading = false;
			})
			



	},
});



export default logSlice.reducer;
