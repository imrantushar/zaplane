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



// work flow listnner
export const workflowNodeListiner = createAsyncThunk(
	'zaplane/workflowNodeListiner',
	async (id, thunkAPI) => {
		try {
			// "start" only registers the listener — no success toast here (it fired
			// an empty one on every start). Success is shown when the trigger is
			// actually captured, in the poll thunk below.
			const res = await API.get(
				namespace + `node-listener/${id}`
			);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
// One fast poll of the listener state. The component calls this ~once a second
// while listening; the backend returns quickly instead of holding the request.
export const workflowNodeListinerPoll = createAsyncThunk(
	'zaplane/workflowNodeListinerPoll',
	async (id, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `node-listener/${id}/poll`
			);
			// Only toast on an actual capture — not while still listening / on
			// stop / on timeout.
			if (res?.data?.code === 'TRIGGERED') {
				handleSliceSuccess(thunkAPI, res?.data?.message);
			}
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const workflowNodeListinerStop = createAsyncThunk(
	'zaplane/workflowNodeListinerStop',
	async (id, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `node-listener/${id}/stop`
			);
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);