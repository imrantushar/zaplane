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

export const testAPI = createAsyncThunk(
	'zaplane/runDynamicApi',
	async ({ method, path, body }, thunkAPI) => {
		try {
			const url = namespace + path;
			let res;

			switch (method) {
				case 'GET':
					res = await API.get(url);
					break;

				case 'POST':
					res = await API.post(url, body);
					break;

				case 'PUT':
					res = await API.put(url, body);
					break;

				case 'DELETE':
					res = await API.delete(url);
					break;

				default:
					throw new Error('Invalid HTTP Method');
			}

			handleSliceSuccess(
				thunkAPI,
				__('API request successful', 'workflow')
			);

			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);




const settingSlice = createSlice({
	name: 'setting',
	initialState: {
		data: [],

	},
	reducers: {

	},
	extraReducers: (builder) => {
		builder
		.addCase(testAPI.fulfilled, (state, action) => {
						state.data = action.payload;
					})
			



	},
});



export default settingSlice.reducer;
