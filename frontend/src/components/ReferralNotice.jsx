import { useEffect, useState } from 'react'
import { Gift, X } from 'lucide-react'
import { referralStateService } from '../services/referral/referralStateService'

export default function ReferralNotice() {
  const [context, setContext] = useState(null)

  useEffect(() => {
    if (window.location.pathname === '/register') return
    const captured = referralStateService.captureFromUrl()
    setContext(captured)
    if (captured?.state === 'detected') {
      referralStateService.resolveCaptured().then(setContext)
    }
  }, [])

  if (!context || ['none', 'notapplicable'].includes(context.state)) return null
  const invalid = ['invalid', 'expired', 'blocked', 'rejected'].includes(context.state)

  return <div className={`fixed inset-x-4 top-4 z-[100] mx-auto max-w-xl rounded-2xl border p-4 shadow-xl ${invalid ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-emerald-200 bg-emerald-50 text-emerald-950'}`}>
    <div className="flex items-start gap-3"><Gift className="mt-0.5 shrink-0" size={20} /><div className="flex-1"><p className="font-black">{invalid ? 'Referral unavailable' : 'Partner referral detected'}</p><p className="mt-1 text-sm">{context.message || (context.businessName ? `Invitation for ${context.businessName}` : 'Continue to securely link this referral after authentication.')}</p></div><button aria-label="Continue without referral" onClick={async () => { await referralStateService.continueWithoutReferral(); setContext(null) }}><X size={20} /></button></div>
  </div>
}
