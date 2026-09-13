import { partnerApi } from '../../api/client'

const CONTEXT_KEY = 'essivery_partner_context'

function payload(response) {
  return response.data?.data ?? response.data
}

function idempotencyKey() {
  if (globalThis.crypto?.randomUUID) {
    return `partner-bootstrap:${globalThis.crypto.randomUUID()}`
  }
  return `partner-bootstrap:${Date.now()}:${Math.random().toString(16).slice(2)}`
}

function save(context) {
  sessionStorage.setItem(CONTEXT_KEY, JSON.stringify(context))
  return context
}

export const partnerContextService = {
  async loadOrBootstrapWithMeta() {
    let initialized = true
    try {
      return { context: save(payload(await partnerApi.get('/me/context'))), initialized }
    } catch (error) {
      if (error?.response?.data?.error?.code !== 'PARTNER_CONTEXT_NOT_INITIALIZED') {
        throw error
      }
      initialized = false
    }

    await partnerApi.post('/me/bootstrap', {}, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return { context: save(payload(await partnerApi.get('/me/context'))), initialized }
  },

  async loadOrBootstrap() {
    return (await this.loadOrBootstrapWithMeta()).context
  },

  get() {
    try {
      const value = sessionStorage.getItem(CONTEXT_KEY)
      return value ? JSON.parse(value) : null
    } catch {
      return null
    }
  },

  clear() {
    sessionStorage.removeItem(CONTEXT_KEY)
  },
}
