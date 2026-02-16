
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



// version releted api
export const getAllVersion = createAsyncThunk(
	'zaplane/getAllVersion',
	async (runId, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + `workflows/${parseInt(runId)}/versions`
			);
			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const getPreviewOldVersion = createAsyncThunk(
	'zaplane/getPreviewOldVersion',
	async ({ id, versionID }, thunkAPI) => {

		try {
			const res = await API.get(
				namespace + `workflows/${id}/versions/${parseInt(versionID)}`
			);

			handleSliceSuccess(thunkAPI, __(' fetched prevews version successfully', 'workflow'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);
export const versionActive = createAsyncThunk(
	'zaplane/versionActive',
	async ({ id, versionID }, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + `workflows/${id}/versions/${parseInt(versionID)}/activate`
			);
			handleSliceSuccess(thunkAPI, __('version active successfully', 'workflow'));

			return res.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);