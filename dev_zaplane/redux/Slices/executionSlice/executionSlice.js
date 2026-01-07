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

export const getRunLive = createAsyncThunk(
	'zaplane/getRunLive',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}/live`
			);

			handleSliceSuccess(
				thunkAPI,
				__('Live run fetched successfully', 'workflow')
			);

			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getRunTimeline = createAsyncThunk(
	'zaplane/getRunTimeline',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}/timeline`
			);

			handleSliceSuccess(
				thunkAPI,
				__('Run timeline fetched successfully', 'workflow')
			);

			return res.data;
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
				namespace + `runs/${parseInt(runId)}/execute`,
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
export const stopRun = createAsyncThunk(
	'zaplane/stopRun',
	async (runId, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `runs/${parseInt(runId)}/stop`
			);

			handleSliceSuccess(
				thunkAPI,
				__('Run stopped successfully', 'workflow')
			);

			return {
				runId: parseInt(runId),
				data: res.data,
			};
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);


const executionSlice = createSlice({
	name: 'execution',
	initialState: {
		data: [],

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
			.addCase(getRunLive.fulfilled, (state, action) => {
				state.data = action.payload;
			})




	},
});



export default executionSlice.reducer;
