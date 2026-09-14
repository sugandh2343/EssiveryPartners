import { AlertCircle, Loader2, X } from 'lucide-react'
import { useState } from 'react'
import { dashboardService } from '../services/partner/dashboardService'

export default function StoreStatusToggle({ isOnline = false, onStatusChange }) {
  const [confirmingOffline, setConfirmingOffline] = useState(false)
  const [updating, setUpdating] = useState(false)
  const [error, setError] = useState('')

  const changeStatus = async nextOnline => {
    if (updating || nextOnline === isOnline) return
    setUpdating(true)
    setError('')
    try {
      const result = await dashboardService.updateStoreStatus(nextOnline)
      onStatusChange?.(result?.store || result || { isOnline: nextOnline })
      setConfirmingOffline(false)
    } catch (requestError) {
      setError(requestError?.response?.data?.error?.userMessage || 'Store status could not be updated. Please try again.')
    } finally {
      setUpdating(false)
    }
  }

  return <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Store status">
    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <p className="text-xs font-black uppercase tracking-[.2em] text-emerald-700">Store Status</p>
        <div className="mt-2 flex items-center gap-3">
          <span className={`h-3 w-3 rounded-full ${isOnline ? 'bg-emerald-500' : 'bg-slate-400'}`} aria-hidden="true" />
          <h2 className="text-xl font-black">{isOnline ? 'Online' : 'Offline'} <span className="text-sm font-bold text-slate-500">{isOnline ? '— Accepting Orders' : '— Not Accepting Orders'}</span></h2>
        </div>
        {error && <p className="mt-3 flex items-center gap-2 text-sm font-bold text-rose-700" role="alert"><AlertCircle size={16} />{error}</p>}
      </div>
      <button type="button" aria-label={isOnline ? 'Go offline' : 'Go online'} disabled={updating} onClick={() => isOnline ? setConfirmingOffline(true) : changeStatus(true)} className="inline-flex min-w-32 cursor-pointer items-center justify-center gap-2 rounded-xl bg-emerald-900 px-5 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-60">
        {updating && <Loader2 size={17} className="animate-spin" />}{isOnline ? 'Go Offline' : 'Go Online'}
      </button>
    </div>

    {confirmingOffline && <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="go-offline-title">
      <div className="relative w-full max-w-md rounded-[2rem] bg-white p-6 shadow-2xl sm:p-8">
        <button type="button" onClick={() => setConfirmingOffline(false)} className="absolute right-5 top-5 cursor-pointer text-slate-400" aria-label="Close"><X size={19} /></button>
        <h3 id="go-offline-title" className="text-2xl font-black">Go Offline?</h3>
        <p className="mt-2 text-slate-600">Customers will temporarily be unable to place new orders from your store.</p>
        <div className="mt-6 flex gap-3">
          <button type="button" disabled={updating} onClick={() => setConfirmingOffline(false)} className="flex-1 cursor-pointer rounded-xl border border-slate-300 px-4 py-3 font-black disabled:cursor-not-allowed disabled:opacity-60">Cancel</button>
          <button type="button" disabled={updating} onClick={() => changeStatus(false)} className="flex-1 cursor-pointer rounded-xl bg-emerald-900 px-4 py-3 font-black text-white disabled:cursor-not-allowed disabled:opacity-60">{updating ? 'Updating…' : 'Go Offline'}</button>
        </div>
      </div>
    </div>}
  </section>
}