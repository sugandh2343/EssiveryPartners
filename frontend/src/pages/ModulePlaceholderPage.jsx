import { ArrowLeft, Construction } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'

const moduleLabels = {
  catalogue: 'Manage Catalogue',
  inventory: 'Inventory',
  orders: 'Orders',
  wallet: 'Partner Wallet',
}

export default function ModulePlaceholderPage() {
  const navigate = useNavigate()
  const { module } = useParams()
  const label = moduleLabels[module] || 'Partner Module'

  return (
    <div className="grid min-h-screen place-items-center bg-[#f4f7f4] p-5 text-slate-950">
      <section className="w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-7 text-center shadow-xl sm:p-10">
        <div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-800">
          <Construction size={28} />
        </div>
        <p className="mt-5 text-xs font-black uppercase tracking-[.2em] text-emerald-700">Partner tools</p>
        <h1 className="mt-2 text-3xl font-black">{label}</h1>
        <p className="mt-3 leading-7 text-slate-600">{label} will be available here in a future implementation phase.</p>
        <button type="button" onClick={() => navigate('/dashboard')} className="mt-7 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-emerald-900 px-5 py-3 font-black text-white">
          <ArrowLeft size={17} /> Back to Dashboard
        </button>
      </section>
    </div>
  )
}