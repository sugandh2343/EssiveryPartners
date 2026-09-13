const normalizeUrl = (value) =>
  String(value || '')
    .trim()
    .replace(/\/+$/, '')

export const ESSIVERY_ROOT =
  'https://essivery.in'

export const API_BASES = {
  partner: normalizeUrl(
    import.meta.env.VITE_API_BASE_URL ||
      `${ESSIVERY_ROOT}/api/partner`
  ),

  user: normalizeUrl(
    import.meta.env.VITE_USER_API_BASE_URL ||
      `${ESSIVERY_ROOT}/api/user`
  ),

  admin: normalizeUrl(
    import.meta.env.VITE_ADMIN_API_BASE_URL ||
      `${ESSIVERY_ROOT}/api/admin`
  ),
}

/*
|--------------------------------------------------------------------------
| Media base
|--------------------------------------------------------------------------
|
| Existing Essivery media/uploads are served from /api.
|
*/

export const MEDIA_BASE_URL =
  normalizeUrl(
    import.meta.env.VITE_MEDIA_BASE_URL ||
      `${ESSIVERY_ROOT}/api`
  )