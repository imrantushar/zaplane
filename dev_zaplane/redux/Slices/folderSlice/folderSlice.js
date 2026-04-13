import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { __ } from '@wordpress/i18n';

import {
    API,
    handleSliceSuccess,
    handleSliceError,
    namespace,
} from '@ZAPUtils/helper';






// Get folders
export const getFolders = createAsyncThunk(
    'zaplane/getFolders',
    async (args = {}, thunkAPI) => {
        try {
            const { page = 1, per_page = 20 } = args;

            const res = await API.get(namespace + "folders", {
                params: { page, per_page },
            });

            const { data, pagination } = res.data;
      

            return {
                data,
                currentPage: pagination.page,
                itemPerPage: pagination.per_page,
                totalItems: pagination.total,
                totalPages: pagination.total_pages,
            };
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

// Create folder
export const createFolder = createAsyncThunk(
    'zaplane/createFolder',
    async ({ title }, thunkAPI) => {
        try {
            const res = await API.post(namespace + "folders", { title });

            handleSliceSuccess(
                thunkAPI,
                __('Folder created successfully', 'zaplane')
            );

            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

// Update folder
export const updateFolder = createAsyncThunk(
    'zaplane/updateFolder',
    async ({ id, title }, thunkAPI) => {
        try {
            const res = await API.patch(
                namespace + `folders/${id}`,
                { title }
            )

            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

// Delete folder
export const deleteFolder = createAsyncThunk(
    'zaplane/deleteFolder',
    async (id, thunkAPI) => {
        try {
            const res = await API.delete(namespace + `folders/${id}`);

            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

// Add workflow
export const addWorkflowToFolder = createAsyncThunk(
    'zaplane/addWorkflowToFolder',
    async ({ folder_id, workflow_id }, thunkAPI) => {
        try {
            const res = await API.post(
                namespace + `folders/${folder_id}/workflows/${workflow_id}`
            );


            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

// Remove workflow
export const removeWorkflowFromFolder = createAsyncThunk(
    'zaplane/removeWorkflowFromFolder',
    async ({ folder_id, workflow_id }, thunkAPI) => {
        try {
            const res = await API.delete(
                namespace + `folders/${folder_id}/workflows/${workflow_id}`
            );



            return res.data;
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);

// Get folder workflows
export const getFolderWorkflows = createAsyncThunk(
    'zaplane/getFolderWorkflows',
    async (args = {}, thunkAPI) => {
        try {
            const { folder_id, page = 1, per_page = 20 } = args;
          

            const res = await API.get(
                namespace + `folders/${folder_id}/workflows`,
                {
                    params: { page, per_page },
                }
            );

            const { data, pagination } = res.data;

            return {
                data,
                folderId: folder_id,
                currentPage: pagination.page,
                itemPerPage: pagination.per_page,
                totalItems: pagination.total,
                totalPages: pagination.total_pages,
            };
        } catch (e) {
            return handleSliceError(thunkAPI, e);
        }
    }
);


const initialState = {
    folders: {
        data: [],
        isLoading: false,
    },

    folderWorkflows: {},
    folderWorkflowsLoading: {},
    folderWorkflowsError: {},

    isCreatingFolder: false,
    isUpdatingFolder: false,
    isDeletingFolder: false,
};
const folderSlice = createSlice({
    name: 'folder',
    initialState,

    reducers: {
        resetFolderState: () => initialState,

        resetLoadingStates: (state) => {
            state.isCreatingFolder = false;
            state.isUpdatingFolder = false;
            state.isDeletingFolder = false;
        },
    },

    extraReducers: (builder) => {
        builder

            .addCase(getFolders.fulfilled, (state, action) => {
                state.folders.isLoading = false;
             

                state.folders.data = action.payload.data;
            })
            .addCase(createFolder.fulfilled, (state, action) => {
                state.isCreatingFolder = false;
                state.folders.data.unshift(action.payload);
            })

            .addCase(updateFolder.fulfilled, (state, action) => {
                state.isUpdatingFolder = false;

                const index = state.folders.data.findIndex(
                    f => f.id === action.payload.id
                );

                if (index !== -1) {
                    state.folders.data[index] = action.payload;
                }
            })
            .addCase(deleteFolder.fulfilled, (state, action) => {
                state.isDeletingFolder = false;

                state.folders.data = state.folders.data.filter(
                    f => f.id !== action.payload.id
                );
            })
            .addCase(addWorkflowToFolder.fulfilled, (state, action) => {
                const index = state.folders.data.findIndex(
                    f => f.id === action.payload.folder_id
                );

                if (index !== -1 && action.payload.workflow_count !== undefined) {
                    state.folders.data[index].workflow_count =
                        action.payload.workflow_count;
                }
            })

            .addCase(removeWorkflowFromFolder.fulfilled, (state, action) => {
                const index = state.folders.data.findIndex(
                    f => f.id === action.payload.folder_id
                );

                if (index !== -1 && action.payload.workflow_count !== undefined) {
                    state.folders.data[index].workflow_count =
                        action.payload.workflow_count;
                }
            })
            .addCase(getFolderWorkflows.fulfilled, (state, action) => {
                const { folderId } = action.payload;
                state.folderWorkflows = {
                    data: action.payload.data,
                    currentPage: action.payload.currentPage,
                    itemPerPage: action.payload.itemPerPage,
                    totalItems: action.payload.totalItems,
                    totalPages: action.payload.totalPages,
                };

                state.folderWorkflowsLoading[folderId] = false;
            })
    },
});



export const { resetFolderState, resetLoadingStates } = folderSlice.actions;

export default folderSlice.reducer;