import { partnerApi } from '../../api/client'

function payload(response) {
  return response.data?.data ?? response.data
}

export const dashboardService = {
  async getRecentOrders() {
    try {
      return payload(await partnerApi.get('/orders', {
        params: { scope: 'dashboard', limit: 10 },
      }))
    } catch (error) {
      if (error?.response?.status === 404) return []
      throw error
    }
  },

  async updateStoreStatus(isOnline) {
    return payload(await partnerApi.put('/store/status', { isOnline }))
  },
}
