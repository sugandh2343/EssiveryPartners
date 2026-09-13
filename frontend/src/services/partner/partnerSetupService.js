import { partnerApi } from '../../api/client'
import { referralStateService } from '../referral/referralStateService'
import { serializeBankDetails } from './bankDetailsContract'

const SETUP_KEY = 'essivery_partner_setup'
let pendingSetup = null

function payload(response) { return response.data?.data ?? response.data }
function requestKey() { return `partner-setup:${globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`}` }
function save(value) { sessionStorage.setItem(SETUP_KEY, JSON.stringify(value)); return value }

export const partnerSetupService = {
  async loadOrBootstrap() {
    if (pendingSetup) return pendingSetup
    pendingSetup = (async () => {
      try {
        return save(payload(await partnerApi.get('/setup')))
      } catch (error) {
        if (error?.response?.data?.error?.code !== 'PARTNER_SETUP_NOT_INITIALIZED') throw error
      }
      return save(payload(await partnerApi.post('/setup/bootstrap', {}, { headers: { 'Idempotency-Key': requestKey() } })))
    })()
    try { return await pendingSetup } finally { pendingSetup = null }
  },
  getCached() {
    try { return JSON.parse(sessionStorage.getItem(SETUP_KEY)) } catch { return null }
  },
  getReferralPrefill() { return referralStateService.getReferralPrefill() },
  async getPersonal() { return payload(await partnerApi.get('/setup/personal')) },
  async savePersonal(input) {
    const result = payload(await partnerApi.put('/setup/personal', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getBusiness() { return payload(await partnerApi.get('/setup/business')) },
  async generateBusinessDescription() { return payload(await partnerApi.post('/setup/business/description-template')) },
  async saveBusiness(input) {
    const result = payload(await partnerApi.put('/setup/business', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getLocation() { return payload(await partnerApi.get('/setup/location')) },
  async saveLocation(input) {
    const result = payload(await partnerApi.put('/setup/location', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getHours() { return payload(await partnerApi.get('/setup/hours')) },
  async saveHours(input) {
    const result = payload(await partnerApi.put('/setup/hours', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getOperations() { return payload(await partnerApi.get('/setup/operations')) },
  async saveOperations(input) {
    const result = payload(await partnerApi.put('/setup/operations', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getDocuments() { return payload(await partnerApi.get('/setup/documents')) },
  async uploadDocument(documentType, file) {
    const body = new FormData(); body.append('documentType', documentType); body.append('file', file)
    return payload(await partnerApi.post('/setup/documents', body, { headers: { 'Content-Type': 'multipart/form-data' } }))
  },
  async getDocumentBlob(reference) { return (await partnerApi.get(`/setup/documents/${encodeURIComponent(reference)}/file`, { responseType: 'blob' })).data },
  async getBank() { return payload(await partnerApi.get('/setup/bank')) },
  async uploadBankProof(file) {
    const body = new FormData(); body.append('file', file)
    return payload(await partnerApi.post('/setup/bank/proof', body))
  },
  async saveBank(input) {
    const result = payload(await partnerApi.put('/setup/bank', serializeBankDetails(input), { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getRetail() { return payload(await partnerApi.get('/setup/retail')) },
  async saveRetail(input) {
    const result = payload(await partnerApi.put('/setup/retail', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getRestaurant() { return payload(await partnerApi.get('/setup/restaurant')) },
  async saveRestaurant(input) {
    const result = payload(await partnerApi.put('/setup/restaurant', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getHomeService() { return payload(await partnerApi.get('/setup/home-service')) },
  async saveHomeService(input) {
    const result = payload(await partnerApi.put('/setup/home-service', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getDelivery() { return payload(await partnerApi.get('/setup/delivery')) },
  async saveDelivery(input) {
    const result = payload(await partnerApi.put('/setup/delivery', input, { headers: { 'Idempotency-Key': requestKey() } }))
    if (result?.setup) save(result.setup)
    return result
  },
  async getReview() { return payload(await partnerApi.get('/setup/review')) },
  async submitReview(input) {
    const result = payload(await partnerApi.post('/setup/submit', input, { headers: { 'Idempotency-Key': requestKey() } }))
    const cached = this.getCached()
    if (cached && result?.application) save({ ...cached, status: result.application.status.toLowerCase().replaceAll('_', ' ').replace(/ (.)/g, (_, c) => c.toUpperCase()), completionPercentage: result.application.completionPercentage, canSubmit: false, submittedAt: result.application.submittedAt })
    return result
  },
  clear() { sessionStorage.removeItem(SETUP_KEY) },
}
