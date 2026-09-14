import { useEffect, useState } from 'react'
import { ArrowRight, Info, Loader2, WalletCards, Zap } from 'lucide-react'

import { walletService } from '../services/partner/walletService'

const DEFAULT_BALANCE = 540

function amount(value) {
  const number = Number(value)
  return Number.isFinite(number) ? number : DEFAULT_BALANCE
}

function formatAmount(value) {
  return `₹${amount(value).toLocaleString('en-IN')}`
}

export default function PartnerWallet({ onViewWallet, onRecharge }) {
  const [summary, setSummary] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true

    walletService.getSummary()
      .then((result) => {
        if (active) setSummary(result || {})
      })
      .catch(() => {
        if (active) setError('Wallet details are temporarily unavailable.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => { active = false }
  }, [])

  const balance = summary?.availableBalance ?? summary?.balance ?? DEFAULT_BALANCE
  const orderUsage = summary?.orderUsage ?? summary?.order_usage
  const availableBenefit = summary?.availableBenefit ?? summary?.available_benefit
  const isZeroBalance = amount(balance) === 0

  return (
    <section className="relative overflow-hidden rounded-[2rem] bg-emerald-950 p-6 text-white shadow-xl sm:p-8">
      <div className="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-emerald-400/20 blur-2xl" />
      <div className="absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-orange-400/15 blur-3xl" />

      <div className="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
        <div>
          <div className="flex items-center gap-3">
            <div className="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-400 text-slate-950">
              <WalletCards size={22} />
            </div>
            <div>
              <h2 className="text-2xl font-black">Partner Wallet</h2>
            </div>
          </div>

          <div className="mt-7 flex flex-wrap items-end gap-x-8 gap-y-4">
            <div>
              <p className="text-sm font-bold text-slate-400">Available Balance</p>
              {loading ? (
                <Loader2 className="mt-2 animate-spin text-emerald-300" size={30} aria-label="Loading wallet balance" />
              ) : (
                <p className="mt-1 text-4xl font-black tracking-tight">{formatAmount(balance)}</p>
              )}
            </div>
            {isZeroBalance && !loading && (
              <p className="rounded-full bg-white/10 px-3 py-2 text-sm font-bold text-emerald-100">Your balance is currently zero.</p>
            )}
          </div>

          {error && (
            <p className="mt-4 inline-flex items-center gap-2 text-sm font-bold text-amber-200">
              <Info size={16} />{error} 
            </p>
          )}

          {(orderUsage != null || availableBenefit != null) && (
            <div className="mt-6 flex flex-wrap gap-3 text-sm">
              {orderUsage != null && <span className="rounded-xl bg-white/10 px-3 py-2 font-bold text-slate-200">Order usage: {orderUsage}</span>}
              {availableBenefit != null && <span className="rounded-xl bg-emerald-400/15 px-3 py-2 font-bold text-emerald-200">Available benefit: {availableBenefit}</span>}
            </div>
          )}
        </div>

        <div className="flex flex-col gap-3 sm:flex-row lg:flex-col">
          <button type="button" onClick={onViewWallet} disabled={!onViewWallet} className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-950 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:bg-white disabled:text-slate-950">
            View Wallet <ArrowRight size={17} />
          </button>
          <button type="button" onClick={onRecharge} disabled={!onRecharge} className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300/50 px-5 py-3 text-sm font-black text-emerald-100 transition hover:border-emerald-200 hover:bg-white/10 disabled:cursor-not-allowed disabled:border-emerald-300/50 disabled:text-emerald-100">
            <Zap size={17} /> Recharge
          </button>
        </div>
      </div>
    </section>
  )
}