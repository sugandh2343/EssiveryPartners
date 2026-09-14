import { ArrowRight } from 'lucide-react'

export default function QuickAction({ icon: Icon, label, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md"
    >
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-800 transition group-hover:bg-emerald-100">
        <Icon size={19} />
      </span>
      <span className="min-w-0 flex-1 truncate font-black text-slate-800">{label}</span>
      <ArrowRight className="shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-emerald-800" size={17} />
    </button>
  )
}