import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	handleSliceSuccess,
	handleSliceError,
	namespace,
	makeRequest,
} from '@ZAPUtils/helper';

// workflow exctions api
export const workFLowExction = createAsyncThunk(
	'zaplane/workFLowExction',
	async (payload, thunkAPI) => {

		try {
			const res = await API.post(
				namespace + 'execute',
				payload
			);

			handleSliceSuccess(thunkAPI, __('Run fetched successfully', 'zaplane'));
			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const workFLowSingeNodeExction = createAsyncThunk(
	'zaplane/workFLowSingeNodeExction',
	async (payload, thunkAPI) => {

		try {
			const res = await API.post(
				namespace + 'execute-node',
				payload
			);
			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);