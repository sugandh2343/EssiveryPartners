import { ArrowLeft, Check, ChevronRight, Circle, Loader2, RotateCcw, Send } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { partnerSetupService } from '../services/partner/partnerSetupService'

const statusLabel = { complete: 'Complete', inProgress: 'In progress', needsCorrection: 'Needs correction', notStarted: 'Not started' }

export default function SetupCenterPage() {
  const navigate = useNavigate()
  const [setup, setSetup] = useState(partnerSetupService.getCached())
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(!setup)
  const load = async () => { setLoading(true); setError(''); try { setSetup(await partnerSetupService.loadOrBootstrap()) } catch (e) { setError(e?.response?.data?.error?.userMessage || 'Business Setup could not be loaded.') } finally { setLoading(false) } }
  useEffect(()=>{ load() },[])

  if (loading && !setup) return <Center><Loader2 className="animate-spin text-emerald-800" size={34}/><p>Preparing Business Setup…</p></Center>
  if (error && !setup) return <Center><p className="font-black">Business Setup unavailable</p><p className="text-sm text-slate-600">{error}</p><button onClick={load} className="inline-flex items-center gap-2 rounded-xl bg-emerald-900 px-4 py-3 font-black text-white"><RotateCcw size={17}/>Try again</button></Center>

  return <div className="min-h-screen bg-[#f4f7f4] text-slate-950"><main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
    <button onClick={()=>navigate('/dashboard')} className="inline-flex items-center gap-2 text-sm font-black text-slate-600"><ArrowLeft size={17}/>Back to Dashboard</button>
    <div className="mt-7 grid gap-6 lg:grid-cols-[.65fr_1.35fr]"><aside className="h-fit rounded-[2rem] bg-emerald-950 p-6 text-white lg:sticky lg:top-6"><p className="text-xs font-black uppercase tracking-[.2em] text-emerald-300">Setup Center</p><h1 className="mt-2 text-3xl font-black">Build your Essivery business profile</h1><p className="mt-3 text-sm leading-6 text-emerald-100">Complete each part progressively. You can return to your Dashboard at any time.</p><div className="mt-7 flex items-end justify-between"><span className="text-4xl font-black">{setup.completionPercentage}%</span><span className="text-xs font-bold">complete</span></div><div className="mt-3 h-2 overflow-hidden rounded-full bg-white/15"><div className="h-full bg-emerald-400" style={{width:`${setup.completionPercentage}%`}}/></div><p className="mt-5 text-sm font-bold capitalize">Application: {setup.status.replace(/([A-Z])/g,' $1')}</p></aside>
      <section><div><p className="text-sm font-bold text-emerald-800">Resumable Business Setup</p><h2 className="mt-1 text-3xl font-black">Complete at your own pace</h2><p className="mt-2 text-slate-600">Official business information will be saved in its appropriate Essivery domain as each form becomes available.</p></div>{(setup.completionPercentage===100||['submitted','resubmitted','underReview','correctionRequired'].includes(setup.status))&&<button onClick={()=>navigate('/setup/review')} className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-900 px-5 py-4 font-black text-white"><Send size={18}/>{['submitted','resubmitted','underReview'].includes(setup.status)?'View Submission':setup.status==='correctionRequired'?'Review Required Corrections':'Review & Submit'}<ChevronRight size={17}/></button>}<div className="mt-6 grid gap-3">{setup.steps.map(step=><article key={step.code} className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center"><span className={`grid h-11 w-11 shrink-0 place-items-center rounded-full ${step.status==='complete'?'bg-emerald-100 text-emerald-800':step.status==='needsCorrection'?'bg-amber-100 text-amber-800':'bg-slate-100 text-slate-500'}`}>{step.status==='complete'?<Check size={20}/>:<Circle size={18}/>}</span><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h3 className="font-black">{step.title}</h3><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-500">{step.required?'Required':step.requirement}</span></div><p className="mt-1 text-sm text-slate-500">{step.description}</p><p className="mt-2 text-xs font-black text-emerald-800">{statusLabel[step.status]||step.status}</p></div>{step.status!=='complete'&&<button onClick={()=>navigate(`/setup/${step.routeCode}`)} className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-900 px-4 py-2 text-sm font-black text-emerald-900">{step.status==='notStarted'?'Start':'Continue'}<ChevronRight size={16}/></button>}</article>)}</div></section>
    </div>
  </main></div>
}

function Center({children}) { return <div className="grid min-h-screen place-content-center gap-4 bg-slate-50 p-6 text-center">{children}</div> }
