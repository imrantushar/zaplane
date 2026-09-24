import { __ } from '@wordpress/i18n';

import {
    API,
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
    ...params
}) {
    const { data } = await API.post(namespace + "dynamic", {
        integration,
        query,
        select,
        where,
        search,
        limit,
        ...params,
    });

    return data;
}
