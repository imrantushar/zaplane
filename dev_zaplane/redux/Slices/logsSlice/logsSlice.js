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
export const replayWorkflowRun = createAsyncThunk(
  'zaplane/replayWorkflowRun',
  async (runId, thunkAPI) => {
    try {
      const res = await API.post(
        namespace + `runs/${parseInt(runId)}/replay`,
        {}
      );

      thunkAPI.dispatch(
        showNotification({
          message: __('Workflow replay started successfully', 'workflow'),
          isShow: true,
          type: 'success',
        })
      );

      return {
        oldRunId: runId,
        newRunId: res?.data?.new_run_id,
      };
    } catch (e) {
      return handleSliceError(thunkAPI, e);
    }
  }
);

const LogSlice = createSlice({
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


export async function fetchDynamic({
	integration,
	query,
	select,
	where = {},
	search = "",
	limit = 20,
}) {
	const { data } = await API.post(namespace + "dynamic", {
		integration,
		query,
		select,
		where,
		search,
		limit,
	});

	return data;
}


export const { createWorkflowTitle } = LogSlice.actions;
export default LogSlice.reducer;
