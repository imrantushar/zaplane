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
            console.log(res)
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
				state.data = [ action.payload, ...state.data ];
			} )

			.addCase( getWorkFlow.fulfilled, ( state, action ) => {
				const { data } =action.payload;
				state.data = data;
				
			} )

			
	},
} );

export const { createWorkflowTitle } = workflowsSlice.actions;
export default workflowsSlice.reducer;
