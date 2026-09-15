import { http } from '@/api/http'

/** @returns {Promise<import('../types').TraceRequest[]>} */
export async function listRequests(options = {}) {
  const response = await http('/api/requests', options)
  return response.data
}

/** @returns {Promise<import('../types').TraceRequest>} */
export async function getRequest(id, options = {}) {
  const response = await http(`/api/requests/${encodeURIComponent(id)}`, options)
  return response.data
}

/**
 * @param {import('../types').CreateRequestInput} input
 * @returns {Promise<import('../types').TraceRequest>}
 */
export async function createRequest(input, options = {}) {
  const response = await http('/api/requests', { ...options, method: 'POST', body: input })
  return response.data
}
/**
 * @param {import('../types').SubmitRequest} input
 * @returns {Promise<import('../types').SubmitRequest}
 */
export async function submitRequest(id, options = {}){
  const response = await http(`/api/requests/${encodeURIComponent(id)}/submit`, {...options, method: 'POST'})
  return response.data
}
