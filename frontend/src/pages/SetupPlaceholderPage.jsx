import { ArrowLeft, Construction } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { partnerSetupService } from '../services/partner/partnerSetupService'

export default function SetupPlaceholderPage() {
  const { step } = useParams()
  const navigate = useNavigate()
  const [setup, setSetup] = useState(partnerSetupService.getCached())
  const [loading, setLoading] = useState(!setup)
  useEffect(()=>{ if (!setup) partnerSetupService.loadOrBootstrap().then(setSetup).catch(()=>setSetup(null)).finally(()=>setLoading(false)) },[setup])
  if (loading) return <div className="grid min-h-screen place-content-center bg-slate-50 font-black">Loading Business Setup…</div>
  const current = setup?.steps?.find(item => item.routeCode === step)
  if (!current) return <div className="grid min-h-screen place-content-center gap-4 bg-slate-50 p-6 text-center"><p className="text-xl font-black">This setup step does not apply to your Partner type.</p><button onClick={()=>navigate('/setup')} className="font-black text-emerald-800">Return to Setup Center</button></div>
  return <div className="grid min-h-screen place-items-center bg-[#f4f7f4] p-5"><section className="w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-7 text-center shadow-xl sm:p-10"><div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-800"><Construction size={28}/></div><p className="mt-5 text-xs font-black uppercase tracking-[.2em] text-emerald-700">Business Setup</p><h1 className="mt-2 text-3xl font-black">{current.title}</h1><p className="mt-3 leading-7 text-slate-600">{current.title} setup will continue here in the next implementation phase. No information has been marked complete or persisted from this placeholder.</p><button onClick={()=>navigate('/setup')} className="mt-7 inline-flex items-center gap-2 rounded-xl bg-emerald-900 px-5 py-3 font-black text-white"><ArrowLeft size={17}/>Back to Setup Center</button></section></div>
}
