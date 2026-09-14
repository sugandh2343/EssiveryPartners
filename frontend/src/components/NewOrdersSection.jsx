import { Loader2 } from 'lucide-react'
import StatusBadge from './StatusBadge'

export default function NewOrdersSection({ orders, loading, error, filter, onFilterChange, onRetry, onView, onViewAll }) {
  return <section className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <p className="text-xs font-black uppercase tracking-[.2em] text-emerald-700">Orders</p>
        <h2 className="mt-1 text-2xl font-black">{filter === 'new' ? 'New Orders' : 'Recent Orders'}</h2>
        <p className="mt-1 text-sm text-slate-500">{filter === 'new' ? 'Orders waiting for your attention.' : 'Your latest order activity.'}</p>
      </div>
      <div className="flex flex-col items-end gap-2">
        <button type="button" onClick={onViewAll} className="inline-flex cursor-pointer items-center gap-1 text-sm font-black text-emerald-800">View All Orders <span aria-hidden="true">→</span></button>
        <div className="flex rounded-xl border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Order filter">
          <button type="button" onClick={() => onFilterChange('new')} className={`cursor-pointer rounded-lg px-3 py-2 text-sm font-black ${filter === 'new' ? 'bg-emerald-900 text-white' : 'text-slate-600'}`}>New Orders</button>
          <button type="button" onClick={() => onFilterChange('recent')} className={`cursor-pointer rounded-lg px-3 py-2 text-sm font-black ${filter === 'recent' ? 'bg-emerald-900 text-white' : 'text-slate-600'}`}>Recent Orders</button>
        </div>
      </div>
    </div>

    {loading ? <div className="mt-8 flex items-center justify-center gap-2 py-12 text-sm font-bold text-slate-500"><Loader2 size={20} className="animate-spin text-emerald-800" />Loading orders…</div> : error ? <div className="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-center"><p className="font-black text-rose-900">Unable to load orders</p><p className="mt-1 text-sm text-rose-800">{error}</p><button type="button" onClick={onRetry} className="mt-4 cursor-pointer rounded-xl bg-rose-900 px-4 py-2 text-sm font-black text-white">Retry</button></div> : <OrderTable orders={orders} filter={filter} onView={onView} />}
  </section>
}

function OrderTable({ orders, filter, onView }) {
  const newStatuses = ['new', 'pending', 'placed']
  const visibleOrders = orders.filter(order => filter === 'new' ? newStatuses.includes(String(order.status || '').toLowerCase()) : true).slice(0, filter === 'new' ? 5 : 10)

  if (visibleOrders.length === 0) return <div className="mt-8 rounded-2xl border border-dashed border-slate-300 p-8 text-center"><p className="font-black">{filter === 'new' ? 'No New Orders' : 'No Recent Orders'}</p><p className="mt-1 text-sm text-slate-500">{filter === 'new' ? 'New orders will appear here when customers place them.' : 'Your recent orders will appear here once order activity begins.'}</p></div>

  return <div className="mt-6 overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm"><thead className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400"><tr>{['Order ID', 'Items', 'Amount', 'Payment', 'Time', 'Status', 'Action'].map(heading => <th key={heading} className="px-3 py-3 font-black">{heading}</th>)}</tr></thead><tbody>{visibleOrders.map(order => <tr key={order.id || order.publicId} className="border-b border-slate-100 last:border-0"><td className="px-3 py-4 font-black">#{order.id || order.publicId || '—'}</td><td className="px-3 py-4">{order.itemsCount ?? order.items ?? 0} Items</td><td className="px-3 py-4 font-black">{formatCurrency(order.amount)}</td><td className="px-3 py-4">{order.paymentType || order.payment || '—'}</td><td className="whitespace-nowrap px-3 py-4 text-slate-500">{formatOrderTime(order.createdAt || order.created_at || order.orderTime)}</td><td className="px-3 py-4"><StatusBadge status={order.status} /></td><td className="px-3 py-4"><button type="button" onClick={() => onView(order)} className="cursor-pointer rounded-lg bg-emerald-900 px-3 py-2 text-xs font-black text-white">View Order</button></td></tr>)}</tbody></table></div>
}

function formatCurrency(value) {
  return `₹${Number(value || 0).toLocaleString('en-IN')}`
}

function formatOrderTime(value) {
  if (!value) return 'Recently'
  const minutes = Math.max(1, Math.round((Date.now() - new Date(value).getTime()) / 60000))
  return minutes < 60 ? `${minutes} min${minutes === 1 ? '' : 's'} ago` : new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })
}
