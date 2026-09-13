import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { Bell, Box, CalendarDays, Check, ChevronRight, Clock3, IndianRupee, LogOut, Menu, PackagePlus, Settings, Store, UserRound, WalletCards } from 'lucide-react'
import { usePartnerSession } from '../context/PartnerSessionContext'
import { usePartnerSelection } from '../context/PartnerSelectionContext'
import { partnerSetupService } from '../services/partner/partnerSetupService'

const actionMap = {
  RETAIL: [['Add Products', PackagePlus], ['Manage Inventory', Box], ['Orders', Menu], ['Business Profile', Store]],
  RESTAURANT: [['Menu', Menu], ['Orders', Box], ['Restaurant Profile', Store], ['Business Hours', Clock3]],
  HOME_SERVICE: [['Services', Settings], ['Bookings', CalendarDays], ['Availability', Clock3], ['Profile', UserRound]],
  DELIVERY: [['Go Online', Store], ['Assignments', Box], ['Earnings', IndianRupee], ['Profile', UserRound]],
}

function statusCopy(context, setup) {
  const approval = String(context?.partner?.approvalStatus || 'pending').toLowerCase()
  const coreSetup = String(context?.partner?.onboardingStatus || 'draft').toLowerCase()
  if (approval === 'suspended') return ['Account restricted', 'Your Partner account is currently restricted. Contact Essivery support for assistance.', 'rose']
  if (['rejected', 'correction_required', 'correction'].includes(approval)) return ['Correction required', 'Review the requested corrections before submitting your business again.', 'amber']
  if (approval === 'approved') return ['Approved Partner', coreSetup === 'completed' ? 'Your business is approved and ready for eligible Partner features.' : 'Your business is approved. Complete the remaining setup when convenient.', 'emerald']
  if (['submitted', 'resubmitted', 'underReview'].includes(setup?.status)) return ['Under Review', 'Your Partner profile has been submitted for Essivery review.', 'blue']
  if (setup?.status === 'correctionRequired') return ['Action required', 'Some Business Setup information needs to be updated.', 'amber']
  if (['submitted', 'pending_review', 'pending'].includes(approval) && coreSetup !== 'draft') return ['Pending review', 'Your application status is controlled by Essivery review.', 'blue']
  return ['Setup incomplete', 'Complete your business setup before submitting it for review.', 'amber']
}

export default function DashboardPage() {
  const { context, logout } = usePartnerSession()
  const { selectedPartner, clearPartner } = usePartnerSelection()
  const location = useLocation()
  const navigate = useNavigate()
  const [setup, setSetup] = useState(partnerSetupService.getCached())
  const [setupError, setSetupError] = useState('')
  const welcomeKey = `essivery_partner_welcome_${context.identity.publicId}`
  const [welcomeOpen, setWelcomeOpen] = useState(() => Boolean(location.state?.showWelcome) && localStorage.getItem(welcomeKey) !== 'shown')
  const referralLinked = Boolean(location.state?.referralLinked)
  const referralMessage = location.state?.referralMessage || ''
  const module = context.identity.module
  const category = selectedPartner?.name || context.identity.type?.replaceAll('_', ' ') || 'Partner'
  const businessName = location.state?.businessName || `${category} Business`
  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening'
  const [statusTitle, statusMessage, statusTone] = statusCopy(context, setup)
  const actions = actionMap[module] || actionMap.RETAIL
  const setupCompleteTitle = module === 'DELIVERY' ? 'Delivery Partner setup information is complete' : 'Business setup information is complete'
  const setupCompleteMessage = module === 'DELIVERY' ? 'Your information is ready to review. This does not mean you are approved, online, or available for delivery jobs.' : 'Your information is ready to review. This does not mean your store is approved or live.'
  useEffect(()=>{ partnerSetupService.loadOrBootstrap().then(setSetup).catch(error=>setSetupError(error?.response?.data?.error?.userMessage || 'Business Setup progress is temporarily unavailable.')) },[])

  const closeWelcome = () => { localStorage.setItem(welcomeKey, 'shown'); setWelcomeOpen(false); navigate('/dashboard', { replace: true }) }
  const signOut = () => { logout(); clearPartner(); navigate('/', { replace: true }) }

  return <div className="min-h-screen bg-[#f4f7f4] text-slate-950">
    <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
        <div className="flex items-center gap-3"><div className="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-900 text-white"><Store size={22}/></div><div><p className="text-lg font-black">Essivery</p><p className="text-[10px] font-black tracking-[.2em] text-emerald-700">PARTNERS</p></div></div>
        <div className="hidden text-right sm:block"><p className="text-sm font-black capitalize">{businessName}</p><p className="text-xs capitalize text-slate-500">{category} Partner</p></div>
        <div className="flex items-center gap-2"><button className="grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white" aria-label="Notifications"><Bell size={18}/></button><button className="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-900" aria-label="Partner profile"><UserRound size={18}/></button><button onClick={signOut} className="grid h-10 w-10 place-items-center rounded-full border border-slate-200 text-slate-600" aria-label="Log out"><LogOut size={18}/></button></div>
      </div>
    </header>

    <main className="mx-auto max-w-7xl space-y-6 px-4 py-7 sm:px-6 sm:py-10">
      <section><p className="text-sm font-bold text-emerald-800">{category} Partner</p><h1 className="mt-1 text-3xl font-black tracking-tight sm:text-4xl">{greeting}, {businessName}</h1><p className="mt-2 text-slate-600">Here&apos;s what&apos;s happening with your Essivery business.</p></section>

      {referralMessage&&<section className="rounded-2xl border border-amber-200 bg-amber-50 p-4"><p className="font-black">Referral not linked</p><p className="mt-1 text-sm text-slate-700">{referralMessage} You can continue using your Partner dashboard.</p></section>}

      <section className={`rounded-2xl border p-4 ${statusTone === 'rose' ? 'border-rose-200 bg-rose-50' : statusTone === 'emerald' ? 'border-emerald-200 bg-emerald-50' : statusTone === 'blue' ? 'border-blue-200 bg-blue-50' : 'border-amber-200 bg-amber-50'}`}><p className="font-black">{statusTitle}</p><p className="mt-1 text-sm text-slate-700">{statusMessage}</p></section>

      <section className="overflow-hidden rounded-[2rem] bg-emerald-950 p-6 text-white shadow-xl sm:p-8">
        <div className="grid gap-7 lg:grid-cols-[1.3fr_.7fr]"><div><p className="text-xs font-black uppercase tracking-[.2em] text-emerald-300">Business Setup</p><div className="mt-2 flex items-end justify-between gap-3"><h2 className="text-2xl font-black">{['submitted','resubmitted','underReview'].includes(setup?.status)?'Application under review':setup?.canSubmit?setupCompleteTitle:'Complete your business setup'}</h2><span className="text-sm font-black">{setup?`${setup.completionPercentage}% complete`:'Loading…'}</span></div><div className="mt-4 h-2 overflow-hidden rounded-full bg-white/15"><div className="h-full rounded-full bg-emerald-400 transition-all" style={{width:`${setup?.completionPercentage||0}%`}}/></div><p className="mt-4 text-sm leading-6 text-emerald-100">{setupError||(setup?.canSubmit?setupCompleteMessage:'Finish your profile to prepare your business for receiving customers on Essivery.')}</p><button onClick={()=>navigate(setup?.canSubmit||['submitted','resubmitted','underReview','correctionRequired'].includes(setup?.status)?'/setup/review':'/setup')} className="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-emerald-950">{setup?.status==='correctionRequired'?'Review corrections':setup?.canSubmit?'Review & Submit':['submitted','resubmitted','underReview'].includes(setup?.status)?'View Submission':'Continue Setup'} <ChevronRight size={17}/></button></div><div className="grid grid-cols-2 gap-2 rounded-2xl bg-white/8 p-4">{setup?.steps?.slice(0,6).map(step=><div key={step.code} className="flex items-center gap-2 text-xs"><span className={`grid h-5 w-5 shrink-0 place-items-center rounded-full ${step.status==='complete'?'bg-emerald-400 text-emerald-950':'border border-white/30'}`}>{step.status==='complete'&&<Check size={13}/>}</span>{step.title}</div>)||<p className="col-span-2 text-sm text-emerald-100">Loading setup steps…</p>}</div></div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[['Today’s Orders','0',Box],['Today’s Sales','₹0',IndianRupee],['Wallet Balance','—',WalletCards],['Active Products / Services','0',Store]].map(([label,value,Icon])=><article key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-800"><Icon size={20}/></div><p className="mt-5 text-3xl font-black">{value}</p><p className="mt-1 text-sm text-slate-500">{label}</p></article>)}</section>

      <section><div className="flex items-center justify-between"><div><h2 className="text-xl font-black">Quick actions</h2><p className="text-sm text-slate-500">More Partner tools will become available as setup progresses.</p></div><span className="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-600">Coming soon</span></div><div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{actions.map(([label,Icon])=><button disabled key={label} className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-left opacity-75"><span className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100"><Icon size={19}/></span><span className="font-black">{label}</span></button>)}</div></section>

      <section className="rounded-[2rem] border border-slate-200 bg-white p-6 sm:p-8"><div className="max-w-2xl"><p className="text-xs font-black uppercase tracking-[.2em] text-emerald-700">Grow with Essivery</p><h2 className="mt-2 text-2xl font-black">You&apos;re almost ready to start receiving customers.</h2><p className="mt-2 text-slate-600">Complete your setup at your own pace. Your Partner dashboard will help you reach nearby customers, manage orders digitally, maintain your catalogue or services, track earnings, and grow your business.</p></div></section>
    </main>

    {welcomeOpen&&<Modal onClose={closeWelcome}><div className="text-center"><div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl">🎉</div><h2 className="mt-5 text-2xl font-black">Welcome to Essivery Partners!</h2><p className="mt-2 text-lg font-bold text-emerald-800">Welcome, {businessName}!</p><p className="mt-3 text-sm leading-6 text-slate-600">Your Essivery Partner account is ready. Explore your dashboard and complete your business setup whenever you&apos;re ready.</p>{referralLinked&&<p className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-black text-emerald-800">Referral linked successfully</p>}<div className="mt-6 grid gap-3 sm:grid-cols-2"><button onClick={closeWelcome} className="rounded-xl bg-emerald-900 px-5 py-3 font-black text-white">Go to Dashboard</button><button onClick={()=>{closeWelcome();navigate('/setup')}} className="rounded-xl border border-emerald-900 px-5 py-3 font-black text-emerald-900">Complete Setup</button></div></div></Modal>}
  </div>
}

function Modal({children,onClose}) { return <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" onMouseDown={e=>{if(e.target===e.currentTarget)onClose()}}><div className="relative w-full max-w-lg rounded-[2rem] bg-white p-6 shadow-2xl sm:p-8">{children}</div></div> }
