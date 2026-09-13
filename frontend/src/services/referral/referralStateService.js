import axios from 'axios'
import { ESSIVERY_ROOT } from '../../config/api'
import { partnerApi } from '../../api/client'

const TOKEN_KEY = 'essivery_partner_referral_token'
const CONTEXT_KEY = 'essivery_partner_referral_context'
const TOKEN_PATTERN = /^[A-Za-z0-9_-]{40,96}$/

function saveSafeContext(value) {
  const safe = value && typeof value === 'object' ? value : { state: 'none', linked: false, prefill: {} }
  sessionStorage.setItem(CONTEXT_KEY, JSON.stringify(safe))
  return safe
}

function requestKey() {
  return `partner-referral:${globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`}`
}

export const referralStateService = {
  captureFromUrl() {
    const url = new URL(window.location.href)
    const canonical = url.searchParams.get('ref')
    const legacy = url.searchParams.get('referralToken')
    url.searchParams.delete('ref')
    url.searchParams.delete('referralToken')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)

    if (canonical && legacy && canonical !== legacy) {
      this.clear()
      return saveSafeContext({ state: 'invalid', linked: false, message: 'This referral link is invalid.', prefill: {} })
    }
    const token = canonical || legacy
    if (!token) return this.getSafeContext()
    if (!TOKEN_PATTERN.test(token)) {
      this.clear()
      return saveSafeContext({ state: 'invalid', linked: false, message: 'This referral link is invalid.', prefill: {} })
    }
    sessionStorage.setItem(TOKEN_KEY, token)
    return saveSafeContext({ state: 'detected', linked: false, message: 'Partner referral detected.', prefill: {} })
  },

  async resolveCaptured() {
    const token = sessionStorage.getItem(TOKEN_KEY)
    if (!token) return this.getSafeContext()
    try {
      const response = await axios.get(`${ESSIVERY_ROOT}/api/user/public/partner-referrals/${encodeURIComponent(token)}`, {
        timeout: 15000,
        headers: { 'X-Client-Type': 'partner_web' },
      })
      const value = response.data?.data ?? response.data
      const safe = {
        state: String(value?.state || 'invalid').toLowerCase(),
        linked: false,
        message: value?.message || 'Partner referral detected.',
        canRegister: value?.canRegister === true,
        businessName: value?.businessName || '',
        businessModule: value?.module || '',
        categoryLabel: value?.categoryLabel || '',
        locality: value?.locality || '',
        pincode: value?.pincode || '',
        maskedMobile: value?.maskedMobile || '',
        identityType: value?.identityType || '',
        category: value?.category && typeof value.category === 'object' ? {
          publicId: value.category.publicId || '',
          code: value.category.code || value?.identityType || '',
          name: value.category.name || '',
          slug: value.category.slug || '',
          image: value.category.image || null,
        } : null,
      }
      if (!safe.canRegister) sessionStorage.removeItem(TOKEN_KEY)
      return saveSafeContext(safe)
    } catch (error) {
      if (error?.response?.status === 404 || error?.response?.status === 422) {
        sessionStorage.removeItem(TOKEN_KEY)
        return saveSafeContext({ state: 'invalid', linked: false, message: 'This referral link is not available.', prefill: {} })
      }
      return this.getSafeContext()
    }
  },

  async completeAfterAuthentication() {
    const token = sessionStorage.getItem(TOKEN_KEY)
    if (token) {
      try {
        const response = await partnerApi.post('/referrals/claim', { referralToken: token }, {
          headers: { 'Idempotency-Key': requestKey() },
        })
        sessionStorage.removeItem(TOKEN_KEY)
        return saveSafeContext(response.data?.data ?? response.data)
      } catch (error) {
        const code = error?.response?.data?.error?.code
        if (['REFERRAL_INVALID', 'REFERRAL_NOT_FOUND', 'REFERRAL_NOT_CLAIMABLE', 'REFERRAL_MODULE_MISMATCH', 'REFERRAL_NOT_APPLICABLE'].includes(code)) {
          sessionStorage.removeItem(TOKEN_KEY)
          return saveSafeContext({ state: 'invalid', linked: false, message: error.response?.data?.error?.userMessage || 'This referral cannot be linked.', prefill: {} })
        }
        throw error
      }
    }
    const response = await partnerApi.get('/referrals/context')
    return saveSafeContext(response.data?.data ?? response.data)
  },

  async continueWithoutReferral() {
    sessionStorage.removeItem(TOKEN_KEY)
    try { await partnerApi.post('/referrals/continue-without-referral', {}) } catch { /* Local state still clears safely. */ }
    return saveSafeContext({ state: 'none', linked: false, prefill: {} })
  },

  getReferralPrefill() {
    return { ...(this.getSafeContext()?.prefill || {}) }
  },

  hasCapturedToken() {
    return Boolean(sessionStorage.getItem(TOKEN_KEY))
  },

  getSafeContext() {
    try { return JSON.parse(sessionStorage.getItem(CONTEXT_KEY)) } catch { return null }
  },

  clear() {
    sessionStorage.removeItem(TOKEN_KEY)
    sessionStorage.removeItem(CONTEXT_KEY)
  },
}
