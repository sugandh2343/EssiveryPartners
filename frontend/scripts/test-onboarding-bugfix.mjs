import assert from 'node:assert/strict'
import { referralPartnerSelection } from '../src/services/referral/referralRegistration.js'
import { serializeBankDetails } from '../src/services/partner/bankDetailsContract.js'

let assertions = 0
const check = (condition, message) => { assertions += 1; assert.ok(condition, message) }

const grocery = { id: 6, public_id: 'CAT_grocery', name: 'Grocery', slug: 'grocery', image: '/grocery.png' }
const restaurant = { id: 7, public_id: 'CAT_restaurant', name: 'Restaurants', slug: 'restaurants', image: '/restaurant.png' }
const context = { canRegister: true, identityType: 'grocery', category: { publicId: 'CAT_grocery', name: 'Grocery', image: '/referral-grocery.png' } }
const selection = referralPartnerSelection(context, [restaurant, grocery])

check(selection?.id === 6, 'Valid Grocery referral did not resolve Grocery')
check(selection?.referralLocked === true, 'Referral selection is not category-locked')
check(selection?.referralIdentityType === 'grocery', 'Referral identity was not retained')
check(selection?.image === '/referral-grocery.png', 'Server referral category image was not retained')
check(referralPartnerSelection({ ...context, identityType: 'restaurant' }, [restaurant, grocery]) === null, 'Client-side identity override was accepted')
check(referralPartnerSelection({ ...context, category: { publicId: 'CAT_restaurant' } }, [restaurant, grocery]) === null, 'Client-side category override was accepted')
check(referralPartnerSelection({ ...context, canRegister: false }, [grocery]) === null, 'Non-claimable referral was accepted')
check(referralPartnerSelection(null, [grocery]) === null, 'Missing referral context was accepted')

const bank = serializeBankDetails({
  accountHolderName: '  Test   Partner  ',
  accountNumber: '0012 3456 7890',
  confirmAccountNumber: '0012 3456 7890',
  ifsc: ' hdfc0001234 ',
  maskedAccountNumber: '•••• 7890',
  reviewStatus: 'pending',
  partnerId: 99,
})
check(Object.keys(bank).join(',') === 'accountHolderName,accountNumber,confirmAccountNumber,ifsc', 'Bank serializer emitted unsupported fields')
check(bank.accountHolderName === 'Test Partner', 'Account holder whitespace was not normalized')
check(bank.accountNumber === '001234567890', 'Account number string/leading zero was not preserved')
check(bank.confirmAccountNumber === '001234567890', 'Confirmation was not normalized consistently')
check(bank.ifsc === 'HDFC0001234', 'Lowercase IFSC was not normalized')
check(!JSON.stringify(bank).includes('••••'), 'Masked account value was submitted')

console.log(`Onboarding bugfix frontend tests passed: ${assertions} assertions`)

