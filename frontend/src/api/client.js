import axios from 'axios'
import { API_BASES } from '../config/api'

const ACCESS_TOKEN_KEY =
  'essivery_partner_access_token'

const REFRESH_TOKEN_KEY =
  'essivery_partner_refresh_token'

let refreshPromise = null


function createClient(baseURL) {
  const client = axios.create({
    baseURL,
    timeout: 20000,

    headers: {
      'Content-Type': 'application/json',
      'X-Client-Type': 'partner_web',
    },
  })

  client.interceptors.request.use(
    (config) => {
      const accessToken =
        sessionStorage.getItem(
          ACCESS_TOKEN_KEY
        )

      if (accessToken) {
        config.headers.Authorization =
          `Bearer ${accessToken}`
      }

      return config
    },
    (error) => Promise.reject(error)
  )

  return client
}


/*
|--------------------------------------------------------------------------
| API clients
|--------------------------------------------------------------------------
*/

export const partnerApi =
  createClient(API_BASES.partner)

partnerApi.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config || {}
    const code = error?.response?.data?.error?.code
    if (error?.response?.status === 401 && code === 'TOKEN_EXPIRED' && !config.__partnerRetried) {
      try {
        config.__partnerRetried = true
        config.headers = config.headers || {}
        config.headers.Authorization = `Bearer ${await refreshAuthTokens()}`
        return partnerApi.request(config)
      } catch (refreshError) {
        clearPartnerSession()
        return Promise.reject(refreshError)
      }
    }
    if (error?.response?.status === 401) {
      clearPartnerSession()
      if (window.location.pathname.startsWith('/dashboard') || window.location.pathname.startsWith('/setup')) {
        window.location.replace('/choose-partner?action=login&reason=session-expired')
      }
    }
    return Promise.reject(error)
  },
)

export const userApi =
  createClient(API_BASES.user)

export const adminApi =
  createClient(API_BASES.admin)


/*
|--------------------------------------------------------------------------
| Token helpers
|--------------------------------------------------------------------------
*/

export function setAuthTokens({
  accessToken,
  refreshToken,
}) {
  if (accessToken) {
    sessionStorage.setItem(
      ACCESS_TOKEN_KEY,
      accessToken
    )
  }

  if (refreshToken) {
    localStorage.setItem(
      REFRESH_TOKEN_KEY,
      refreshToken
    )
  }
}

export function getAccessToken() {
  return sessionStorage.getItem(
    ACCESS_TOKEN_KEY
  )
}

export function getRefreshToken() {
  return localStorage.getItem(
    REFRESH_TOKEN_KEY
  )
}

export function hasRefreshToken() {
  return Boolean(getRefreshToken())
}

export async function refreshAuthTokens() {
  if (refreshPromise) return refreshPromise
  const refreshToken = getRefreshToken()
  if (!refreshToken) throw new Error('A refresh token is required.')
  refreshPromise = axios.post(`${API_BASES.user}/auth/refresh`, { refreshToken }, {
    timeout: 20000,
    headers: { 'Content-Type': 'application/json', 'X-Client-Type': 'partner_web' },
  }).then((response) => {
    const value = response.data?.data ?? response.data
    if (!value?.accessToken || !value?.refreshToken) throw new Error('Session refresh response was invalid.')
    setAuthTokens(value)
    return value.accessToken
  }).catch((error) => {
    clearPartnerSession()
    throw error
  }).finally(() => { refreshPromise = null })
  return refreshPromise
}

function clearPartnerSession() {
  clearAuthTokens()
  sessionStorage.removeItem('essivery_partner_context')
  sessionStorage.removeItem('essivery_partner_setup')
}

export function clearAuthTokens() {
  sessionStorage.removeItem(
    ACCESS_TOKEN_KEY
  )

  localStorage.removeItem(
    REFRESH_TOKEN_KEY
  )
}


/*
|--------------------------------------------------------------------------
| Default client = Partner API
|--------------------------------------------------------------------------
*/

export default partnerApi
