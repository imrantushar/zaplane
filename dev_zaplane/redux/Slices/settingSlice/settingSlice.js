import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
	API,
	handleSliceSuccess,
	handleSliceError,
	namespace,
	settings as injectedSettings,
} from '@ZAPUtils/helper';

export const getSettings = createAsyncThunk(
	'zaplane/getSettings',
	async (_, thunkAPI) => {
		try {
			const res = await API.get(namespace + 'settings');
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

export const saveSettings = createAsyncThunk(
	'zaplane/saveSettings',
	async (payload, thunkAPI) => {
		try {
			const res = await API.post(namespace + 'settings', payload);
			handleSliceSuccess(thunkAPI, __('Settings saved', 'zaplane'));
			return res.data;
		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);

const settingSlice = createSlice({
	name: 'setting',
	initialState: {
		// Seed from the server-injected snapshot so the UI renders instantly,
		// then refresh via getSettings().
		data: injectedSettings || null,
		loading: false,
		saving: false,
	},
	reducers: {},
	extraReducers: (builder) => {
		builder
			.addCase(getSettings.pending, (state) => {
				state.loading = true;
			})
			.addCase(getSettings.fulfilled, (state, action) => {
				state.loading = false;
				state.data = action.payload;
			})
			.addCase(getSettings.rejected, (state) => {
				state.loading = false;
			})
			.addCase(saveSettings.pending, (state) => {
				state.saving = true;
			})
			.addCase(saveSettings.fulfilled, (state, action) => {
				state.saving = false;
				state.data = action.payload;
			})
			.addCase(saveSettings.rejected, (state) => {
				state.saving = false;
			});
	},
});

export default settingSlice.reducer;
