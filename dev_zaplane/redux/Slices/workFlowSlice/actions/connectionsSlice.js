import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
    API,
    handleSliceSuccess,
    handleSliceError,
    namespace,
    makeRequest,
} from '@ZAPUtils/helper';


export const fetchConnectionsByApp = createAsyncThunk(
	'zaplane/fetchConnectionsByApp',
	async (appSlug, thunkAPI) => {
		try {
			const res = await API.get(
				namespace + "connections",
				{
					params: { app: appSlug },
				}
			);

			// assuming backend returns { data: [...] }
			return res?.data?.data || res?.data;

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);