import { partnerApi } from '../../api/client'

function payload(response) {
  return response.data?.data ?? response.data
}

const demoAlerts = [
  { id: 'amul-gold-milk-500ml', productName: 'Amul Gold Milk 500ml', status: 'Only 2 left', actionLabel: 'Update Stock' },
  { id: 'coca-cola-750ml', productName: 'Coca Cola 750ml', status: 'Out of Stock', actionLabel: 'Restock' },
  { id: 'britannia-bread-400g', productName: 'Britannia Bread 400g', status: 'Only 3 left', actionLabel: 'Update Stock' },
  { id: 'aashirvaad-atta-5kg', productName: 'Aashirvaad Atta 5kg', status: 'Only 4 left', actionLabel: 'Update Stock' },
  { id: 'lays-magic-masala-52g', productName: 'Lay\'s Magic Masala 52g', status: 'Out of Stock', actionLabel: 'Restock' },
]

export const inventoryService = {
  async getAlerts() {
    try {
      const result = payload(await partnerApi.get('/inventory/alerts'))
      return Array.isArray(result) ? result : result?.alerts || result?.items || []
    } catch (error) {
      if (error?.response?.status === 404) return demoAlerts
      throw error
    }
  },
}