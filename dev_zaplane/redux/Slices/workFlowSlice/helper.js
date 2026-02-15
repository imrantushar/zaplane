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
export async function fetchDynamic({
    integration,
    query,
    select,
    where = {},
    search = "",
    limit = 20,
}) {
    const { data } = await API.post(namespace + "dynamic", {
        integration,
        query,
        select,
        where,
        search,
        limit,
    });

    return data;
}
