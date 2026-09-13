export function serializeBankDetails(input = {}) {
  return {
    accountHolderName: String(input.accountHolderName || '').trim().replace(/\s+/g, ' '),
    accountNumber: String(input.accountNumber || '').replace(/\s+/g, ''),
    confirmAccountNumber: String(input.confirmAccountNumber || '').replace(/\s+/g, ''),
    ifsc: String(input.ifsc || '').trim().toUpperCase(),
  }
}

