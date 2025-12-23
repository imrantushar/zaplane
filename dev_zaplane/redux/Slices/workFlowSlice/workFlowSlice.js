import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';

import {
	API,
	current_user_can,
	current_user_id,
	is_admin,
	handleSliceSuccess,
	handleSliceError,
} from '@ZAPUtils/helper';
const namespace = 'zaplane/v1/';

export const createWorkflows = createAsyncThunk(
	'zaplane/createWorkflows',
	async ( payload, thunkAPI ) => {
		return await API.post( namespace + 'workflows', payload )
			.then( ( res ) => {
				console.log(res,'response');

					handleSliceSuccess( thunkAPI,"data fetched successfully" );
					return res.data;
			} )
			.catch( ( err ) => {
				return handleSliceError( thunkAPI, "Data fetching failed" );
			} );
	}
);

export const getWorkFlow = createAsyncThunk(
    'zaplane/getWorkFlow',
    async (ID, thunkAPI) => {
        try {
            const res = await API.get(namespace + "workflows");
           return res.data
        } catch (e) {
            thunkAPI.dispatch(
                showNotification({
                    message: e,
                    isShow: true,
                    type: 'error',
                })
            );
        }
    }
);
export const updateWorkFlow = createAsyncThunk(
	'zaplane/updateWorkFlow',
	async ({ id, payload }, thunkAPI) => {
		try {
			const res = await API.post(
				namespace + "workflows/" + parseInt(id),
				payload
			);
			thunkAPI.dispatch(
				showNotification({
					message: __('Updated Orders Successfully', 'workflow'),
					isShow: true,
					type: 'success',
				})
			);
			return res.data;
		} catch (e) {
			thunkAPI.dispatch(
				showNotification({
					message: e,
					isShow: true,
					type: 'error',
				})
			);
		}
	}
);


const workflowsSlice = createSlice( {
	name: 'workflows',
	initialState: {
     data:[],
	workflow_Title: '',
		
	},
	reducers: {
		createWorkflowTitle: ( state, action ) => {
			state.workflow_Title = action.payload;
		}
		
	},
	extraReducers: ( builder ) => {
		builder
			.addCase( createWorkflows.fulfilled, ( state, action ) => {
				state.data=action.payload;
			} )

			.addCase( getWorkFlow.fulfilled, ( state, action ) => {
				console.log(action,'action',state);
				state.data = action.payload;
				
			} )
			.addCase(updateWorkFlow.fulfilled, (state, action) => {
				state.data = state.data.map((item) => {
					if (parseInt(item.id) === parseInt(action.payload.id)) {
						return { ...item, ...action.payload };
					}
					return item;
				});
			})

			
	},
} );

export const { createWorkflowTitle } = workflowsSlice.actions;
export default workflowsSlice.reducer;
