import { partnerApi } from '../../api/client'

function payload(response) {
  return response.data?.data ?? response.data
}

export const walletService = {
  async getSummary() {
    return payload(await partnerApi.get('/wallet/summary'))
  },
}