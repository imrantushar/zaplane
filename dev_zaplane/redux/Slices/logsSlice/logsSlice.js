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
	async ({ limit = 50, offset = 0 } = {}, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs?limit=${limit}&offset=${offset}`
			);

			handleSliceSuccess(
				thunkAPI,
				__('Runs fetched successfully', 'workflow')
			);

			return res.data; 
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

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
			.addCase(getRunsList.fulfilled, (state, action) => {
				state.data = action.payload;
			})
			



	},
});



export default logSlice.reducer;
