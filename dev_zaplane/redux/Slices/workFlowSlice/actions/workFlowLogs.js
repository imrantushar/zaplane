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


// log api
export const nodeLogsRunDetails = createAsyncThunk(
	'zaplane/nodeLogsRunDetails',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}`
			);

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getNodeLogDetails = createAsyncThunk(
	'zaplane/getNodeLogDetails',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `runs/${parseInt(runId)}`
			);

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);