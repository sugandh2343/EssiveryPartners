import { AlertTriangle, Gift, Loader2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import ChoosePartnerPage from './ChoosePartnerPage'
import { partnerApi } from '../api/client'
import { usePartnerSelection } from '../context/PartnerSelectionContext'
import { referralStateService } from '../services/referral/referralStateService'
import { referralPartnerSelection } from '../services/referral/referralRegistration'

function payload(response) { return response.data?.data ?? response.data }

export default function ReferralRegistrationPage() {
  const navigate = useNavigate()
  const { selectPartner, clearPartner } = usePartnerSelection()
  const [state, setState] = useState({ status: 'loading', context: null })

  useEffect(() => {
    let active = true
    ;(async () => {
      const captured = referralStateService.captureFromUrl()
      if (!referralStateService.hasCapturedToken()) {
        if (active) setState({ status: captured?.state === 'invalid' ? 'invalid' : 'normal', context: captured })
        return
      }

      const context = await referralStateService.resolveCaptured()
      if (!active) return
      if (!context?.canRegister) {
        clearPartner()
        setState({ status: 'invalid', context })
        return
      }

      try {
        const categories = payload(await partnerApi.get('/catalogue/parent-categories')) || []
        const selection = referralPartnerSelection(context, categories)
        if (!selection) throw new Error('The referred Partner category is unavailable.')
        selectPartner(selection)
        setState({ status: 'ready', context })
        navigate('/login', { replace: true })
      } catch {
        clearPartner()
        setState({ status: 'invalid', context: { ...context, message: 'The referred Partner category is currently unavailable.' } })
      }
    })()
    return () => { active = false }
  }, [clearPartner, navigate, selectPartner])

  if (state.status === 'normal') return <ChoosePartnerPage />

  if (state.status === 'invalid') {
    return <main className="grid min-h-screen place-items-center bg-slate-50 p-5"><section className="w-full max-w-lg rounded-[2rem] border border-amber-200 bg-white p-8 text-center shadow-xl"><AlertTriangle className="mx-auto text-amber-600" size={42}/><h1 className="mt-4 text-2xl font-black">Referral unavailable</h1><p className="mt-3 text-slate-600">{state.context?.message || 'This referral link is invalid or unavailable.'}</p><button className="mt-6 rounded-xl bg-emerald-900 px-5 py-3 font-black text-white" onClick={async()=>{await referralStateService.continueWithoutReferral();clearPartner();setState({status:'normal',context:null})}}>Choose a Partner category</button></section></main>
  }

  return <main className="grid min-h-screen place-items-center bg-slate-50 p-5"><section className="w-full max-w-lg rounded-[2rem] border border-emerald-200 bg-white p-8 text-center shadow-xl"><Gift className="mx-auto text-emerald-700" size={42}/><h1 className="mt-4 text-2xl font-black">{state.context?.category?.name ? `Referred for ${state.context.category.name}` : 'Resolving Partner referral'}</h1><p className="mt-3 text-slate-600">Your referral category is being verified securely.</p><Loader2 className="mx-auto mt-6 animate-spin text-emerald-800"/></section></main>
}

