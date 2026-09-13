import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react'
import { clearAuthTokens, getAccessToken, hasRefreshToken, refreshAuthTokens } from '../api/client'
import { partnerContextService } from '../services/partner/partnerContextService'
import { partnerSetupService } from '../services/partner/partnerSetupService'

const PartnerSessionContext = createContext(null)

export function PartnerSessionProvider({ children }) {
  const [context, setContext] = useState(() => (getAccessToken() || hasRefreshToken()) ? partnerContextService.get() : null)
  const [loading, setLoading] = useState(false)
  const pendingEnsure = useRef(null)

  const ensure = useCallback(async () => {
    if (pendingEnsure.current) return pendingEnsure.current
    pendingEnsure.current = (async () => {
      if (!getAccessToken()) {
        if (!hasRefreshToken()) return null
        await refreshAuthTokens()
      }
      setLoading(true)
      try {
        const result = await partnerContextService.loadOrBootstrapWithMeta()
        setContext(result.context)
        return result
      } finally {
        setLoading(false)
      }
    })()
    try { return await pendingEnsure.current } finally { pendingEnsure.current = null }
  }, [])

  const accept = useCallback((nextContext) => {
    setContext(nextContext)
  }, [])

  const logout = useCallback(() => {
    clearAuthTokens()
    partnerContextService.clear()
    partnerSetupService.clear()
    setContext(null)
  }, [])

  const value = useMemo(() => ({ context, loading, ensure, accept, logout }), [context, loading, ensure, accept, logout])
  return <PartnerSessionContext.Provider value={value}>{children}</PartnerSessionContext.Provider>
}

export function usePartnerSession() {
  const value = useContext(PartnerSessionContext)
  if (!value) throw new Error('usePartnerSession must be used inside PartnerSessionProvider')
  return value
}
