const STATUS_STYLES = {
  new: 'border border-slate-300 bg-white text-black',
  accepted: 'border border-emerald-200 bg-emerald-50 text-black',
  preparing: 'border border-amber-200 bg-amber-100 text-black',
  ready: 'border border-cyan-200 bg-cyan-100 text-black',
  picked_up: 'border border-violet-200 bg-violet-100 text-black',
  pending: 'border border-blue-200 bg-blue-100 text-black',
  completed: 'border border-emerald-200 bg-emerald-100 text-black',
  cancelled: 'border border-red-200 bg-red-100 text-black',
  rejected: 'border border-rose-200 bg-rose-100 text-black',
}

const STATUS_LABELS = {
  new: 'New',
  accepted: 'Accepted',
  preparing: 'Preparing',
  ready: 'Ready',
  picked_up: 'Picked Up',
  pending: 'Pending',
  completed: 'Completed',
  cancelled: 'Cancelled',
  rejected: 'Rejected',
}

export default function StatusBadge({ status = 'new' }) {
  const normalizedStatus = normalizeStatus(status)
  const label = STATUS_LABELS[normalizedStatus] || formatUnknownStatus(normalizedStatus)
  const style = STATUS_STYLES[normalizedStatus] || 'border border-slate-200 bg-slate-100 text-black'

  return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-black ${style}`} aria-label={`Order status: ${label}`}>{label}</span>
}

function normalizeStatus(status) {
  return String(status ?? 'unknown')
    .trim()
    .toLowerCase()
    .replace(/[-\s]+/g, '_')
    .replace(/_+/g, '_') || 'unknown'
}

function formatUnknownStatus(status) {
  if (status === 'unknown') return 'Unknown'
  return status.replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase())
}
