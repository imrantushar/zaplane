import { API, namespace } from '@ZAPUtils/helper';

const base = namespace + 'custom-apps';

export const fetchCustomApps = () => API.get(base).then((r) => r.data);

export const fetchCustomApp = (slug) =>
	API.get(`${base}/${slug}`).then((r) => r.data);

export const createCustomApp = (manifest) =>
	API.post(base, manifest).then((r) => r.data);

export const updateCustomApp = (slug, manifest) =>
	API.put(`${base}/${slug}`, manifest).then((r) => r.data);

export const deleteCustomApp = (slug) =>
	API.delete(`${base}/${slug}`).then((r) => r.data);

export const importCustomApp = (manifest) =>
	API.post(`${base}/import`, manifest).then((r) => r.data);

/**
 * Run one request template live (the builder's "Send test request").
 * @param {{manifest:object, request:object, config:object, credentials:object}} payload
 */
export const testRequest = (payload) =>
	API.post(`${base}/test-request`, payload).then((r) => r.data);

export const testConnection = (slug, credentials) =>
	API.post(`${base}/${slug}/test-connection`, { credentials }).then((r) => r.data);
