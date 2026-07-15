import {  createAsyncThunk} from '@reduxjs/toolkit';
import { API, namespace, handleSliceError } from '@ZAPUtils/helper';


export const conditionVariables = createAsyncThunk(
	'zaplane/conditionVariables',
	async (payload, thunkAPI) => {
		try {
			const res = await API.post(namespace + `condition-variables`,payload);
			return res.data

		} catch (e) {
			return handleSliceError(thunkAPI, e);
		}
	}
);