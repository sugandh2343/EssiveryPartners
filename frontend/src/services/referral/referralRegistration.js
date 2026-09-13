const IDENTITY_BY_SLUG = {
  grocery: 'grocery',
  vegetable: 'vegetable',
  vegetables: 'vegetable',
  'fresh-fruits-and-vegetables': 'vegetable',
  pharmacy: 'pharmacy',
  fashion: 'fashion',
  electronics: 'electronics',
  restaurant: 'restaurant',
  restaurants: 'restaurant',
  'home-service': 'home_service',
  'home-services': 'home_service',
}

function identityFromSlug(slug) {
  const normalized = String(slug || '').trim().toLowerCase()
  return IDENTITY_BY_SLUG[normalized] || normalized.replaceAll('-', '_')
}

export function referralPartnerSelection(context, categories) {
  const publicId = String(context?.category?.publicId || '')
  const identityType = String(context?.identityType || '')
  if (context?.canRegister !== true || !publicId || !identityType) return null

  const category = categories.find((item) => String(item.public_id) === publicId)
  if (!category || identityFromSlug(category.slug) !== identityType) return null

  return {
    id: category.id,
    public_id: category.public_id,
    name: context.category.name || category.name,
    slug: category.slug,
    image: context.category.image || category.image || null,
    type: 'business',
    referralLocked: true,
    referralIdentityType: identityType,
  }
}

