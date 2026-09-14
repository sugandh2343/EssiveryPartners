import { partnerApi } from '../../api/client'

function payload(response) {
  return response.data?.data ?? response.data
}

const demoOrders = [
  { id: 'ESS10241', itemsCount: 4, amount: 640, paymentType: 'COD', createdAt: new Date(Date.now() - 2 * 60000).toISOString(), status: 'new' },
  { id: 'ESS10240', itemsCount: 2, amount: 270, paymentType: 'Online', createdAt: new Date(Date.now() - 8 * 60000).toISOString(), status: 'new' },
  { id: 'ESS10239', itemsCount: 6, amount: 1180, paymentType: 'COD', createdAt: new Date(Date.now() - 24 * 60000).toISOString(), status: 'new' },
  { id: 'ESS10238', itemsCount: 3, amount: 450, paymentType: 'Online', createdAt: new Date(Date.now() - 42 * 60000).toISOString(), status: 'completed' },
  { id: 'ESS10237', itemsCount: 1, amount: 95, paymentType: 'COD', createdAt: new Date(Date.now() - 65 * 60000).toISOString(), status: 'completed' },
]

export const dashboardService = {
  async getRecentOrders() {
    try {
      return payload(await partnerApi.get('/orders', {
        params: { scope: 'dashboard', limit: 10 },
      }))
    } catch (error) {
      if (error?.response?.status === 404) return demoOrders
      throw error
    }
  },

  async updateStoreStatus(isOnline) {
    return payload(await partnerApi.put('/store/status', { isOnline }))
  },
}