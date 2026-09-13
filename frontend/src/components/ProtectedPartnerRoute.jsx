import { useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { getAccessToken, hasRefreshToken } from '../api/client'
import { usePartnerSession } from '../context/PartnerSessionContext'

const hasPartnerSession = () => Boolean(getAccessToken() || hasRefreshToken())

export default function ProtectedPartnerRoute({ children }) {
  const { context, ensure } = usePartnerSession()
  const [state, setState] = useState(() => !hasPartnerSession() ? 'guest' : context ? 'ready' : 'loading')
  const [message, setMessage] = useState('')

  useEffect(() => {
    if (!hasPartnerSession()) { setState('guest'); return }
    if (context) { setState('ready'); return }
    let active=true
    ensure().then(result=>{if(active)setState(result?.context?'ready':'guest')}).catch(error=>{if(!active)return;const status=error?.response?.status,code=error?.response?.data?.error?.code;if(status===401){setState('guest');return}if(status===403||['PARTNER_IDENTITY_REQUIRED','PARTNER_IDENTITY_UNSUPPORTED','PARTNER_CONTEXT_AMBIGUOUS'].includes(code)){setState('forbidden');return}setMessage(error?.response?.data?.error?.userMessage||'Essivery is temporarily busy. Your session is still active.');setState('retryable')})
    return()=>{active=false}
  }, [context, ensure])

  if (state === 'guest') return <Navigate to="/choose-partner?action=login" replace />
  if (state === 'forbidden') return <Navigate to="/choose-partner?action=login&reason=identity" replace />
  if (state === 'retryable') return <div className="grid min-h-screen place-content-center gap-4 bg-slate-50 p-6 text-center"><p className="font-black">Dashboard temporarily unavailable</p><p className="max-w-md text-sm text-slate-600">{message}</p><button onClick={()=>{setMessage('');setState('loading');ensure().then(result=>setState(result?.context?'ready':'guest')).catch(error=>{setMessage(error?.response?.data?.error?.userMessage||'Please wait and try again.');setState('retryable')})}} className="rounded-xl bg-emerald-900 px-5 py-3 font-black text-white">Try again</button></div>
  if (!context || state === 'loading') return <div className="grid min-h-screen place-items-center bg-slate-50"><div className="h-10 w-10 animate-spin rounded-full border-4 border-emerald-700 border-t-transparent" aria-label="Loading Partner dashboard" /></div>
  return children
}
