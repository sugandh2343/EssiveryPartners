import { useEffect, useState } from 'react'
import { AlertTriangle, ArrowRight, Boxes, Loader2, RefreshCw } from 'lucide-react'
import { useNavigate } from 'react-router-dom'

import { inventoryService } from '../services/partner/inventoryService'

function alertItem(item) {
  const productName = item.productName || item.product_name || item.name || 'Unnamed product'
  const status = item.status || item.stockStatus || item.stock_status || 'Needs attention'
  const outOfStock = String(status).toLowerCase().includes('out')
  return {
    id: item.id || item.publicId || productName,
    productName,
    status,
    actionLabel: item.actionLabel || item.action_label || (outOfStock ? 'Restock' : 'Update Stock'),
    outOfStock,
  }
}

export default function InventoryAlerts() {
  const navigate = useNavigate()
  const [alerts, setAlerts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const loadAlerts = () => {
    setLoading(true)
    setError('')
    inventoryService.getAlerts()
      .then((result) => setAlerts((result || []).slice(0, 5).map(alertItem)))
      .catch((requestError) => setError(requestError?.response?.data?.error?.userMessage || 'Inventory alerts could not be loaded.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { loadAlerts() }, [])

  const openInventory = (item) => {
    const query = item ? `?product=${encodeURIComponent(item.id)}&action=stock` : ''
    navigate(`/inventory${query}`)
  }

  return (
    <section className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex items-start gap-3">
          <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-800">
            <Boxes size={22} />
          </div>
          <div>
            <h2 className="mt-1 text-2xl font-black">Inventory Alerts</h2>
            <p className="mt-1 text-sm text-slate-500">Products that need attention before your next order.</p>
          </div>
        </div>
        <button type="button" onClick={() => openInventory()} className="inline-flex cursor-pointer items-center gap-2 text-sm font-black text-emerald-800 transition hover:text-emerald-950">
          View Inventory <ArrowRight size={17} />
        </button>
      </div>

      {loading && (
        <div className="mt-6 flex items-center justify-center gap-3 rounded-2xl bg-slate-50 p-8 text-sm font-bold text-slate-500">
          <Loader2 className="animate-spin text-emerald-800" size={20} /> Loading inventory alerts...
        </div>
      )}

      {!loading && error && (
        <div className="mt-6 flex flex-col gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800 sm:flex-row sm:items-center sm:justify-between">
          <p className="inline-flex items-center gap-2 font-bold"><AlertTriangle size={18} />{error}</p>
          <button type="button" onClick={loadAlerts} className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-rose-700 px-4 py-2 font-black text-white"><RefreshCw size={16} /> Try Again</button>
        </div>
      )}

      {!loading && !error && alerts.length === 0 && (
        <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
          <p className="font-black text-emerald-950">No Inventory Alerts</p>
          <p className="mt-1 text-sm text-emerald-800">Everything looks good. No products require attention.</p>
        </div>
      )}

      {!loading && !error && alerts.length > 0 && (
        <div className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {alerts.map((item) => (
            <article key={item.id} className="flex min-h-32 flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div className="flex items-start gap-3">
                <span className={`mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl ${item.outOfStock ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800'}`}>
                  <AlertTriangle size={17} />
                </span>
                <div className="min-w-0">
                  <h3 className="truncate font-black text-slate-950" title={item.productName}>{item.productName}</h3>
                  <p className={`mt-1 text-sm font-bold ${item.outOfStock ? 'text-rose-700' : 'text-amber-800'}`}>{item.status}</p>
                </div>
              </div>
              <button type="button" onClick={() => openInventory(item)} className="mt-4 inline-flex cursor-pointer items-center gap-2 self-start text-sm font-black text-emerald-800 hover:text-emerald-950">
                {item.actionLabel} <ArrowRight size={15} />
              </button>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}