import { partnerApi } from '../../api/client'

function payload(response) {
  return response.data?.data ?? response.data
}

export const inventoryService = {
  async getAlerts() {
    try {
      const result = payload(await partnerApi.get('/inventory/alerts'))
      return Array.isArray(result) ? result : result?.alerts || result?.items || []
    } catch (error) {
      if (error?.response?.status === 404) return []
      throw error
    }
  },
}
