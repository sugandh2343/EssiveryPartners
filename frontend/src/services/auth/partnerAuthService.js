import {
  userApi,
  setAuthTokens,
  clearAuthTokens,
} from '../../api/client'

import {
  firebasePhoneAuthService,
} from '../firebase/firebasePhoneAuthService'

import {
  partnerContextService,
} from '../partner/partnerContextService'


/*
|--------------------------------------------------------------------------
| Normalize Partner Identity
|--------------------------------------------------------------------------
|
| The selected partner comes from:
|
| Parent categories:
| {
|   id: 1,
|   name: 'Grocery',
|   slug: 'grocery',
|   type: 'business'
| }
|
| Delivery:
| {
|   id: null,
|   name: 'Delivery Partner',
|   slug: 'delivery-partner',
|   type: 'delivery'
| }
|
*/

function normalizeIdentityType(selectedPartner) {
  if (!selectedPartner) {
    throw new Error(
      'Partner category is required before authentication.'
    )
  }

  /*
   * Delivery is a special Essivery identity.
   */
  if (
    selectedPartner.type === 'delivery' ||
    selectedPartner.slug === 'delivery-partner' ||
    selectedPartner.slug === 'delivery_partner'
  ) {
    return 'delivery_partner'
  }

  const slug = String(
    selectedPartner.slug || ''
  )
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')


  /*
   * Explicit frontend normalization.
   *
   * Backend also validates/normalizes identities,
   * but frontend should still send canonical values.
   */
  const identityMap = {
    grocery: 'grocery',

    vegetable: 'vegetable',
    vegetables: 'vegetable',
    'fruits-vegetables': 'vegetable',
    'fruits-and-vegetables': 'vegetable',
    'fresh-fruits-and-vegetables': 'vegetable',

    pharmacy: 'pharmacy',

    fashion: 'fashion',

    electronics: 'electronics',

    restaurant: 'restaurant',
    restaurants: 'restaurant',

    'home-service': 'home_service',
    'home-services': 'home_service',
    homeservice: 'home_service',
    homeservices: 'home_service',

    'delivery-partner': 'delivery_partner',
    delivery_partner: 'delivery_partner',
  }

  const identityType =
    identityMap[slug] || slug.replace(/-/g, '_')

  if (!identityType) {
    throw new Error(
      'Unable to determine partner identity.'
    )
  }

  if (
    selectedPartner.referralLocked &&
    selectedPartner.referralIdentityType !== identityType
  ) {
    throw new Error(
      'The referred Partner category could not be verified.'
    )
  }

  return selectedPartner.referralLocked
    ? selectedPartner.referralIdentityType
    : identityType
}


/*
|--------------------------------------------------------------------------
| Parent Category
|--------------------------------------------------------------------------
*/

function getParentCategoryId(
  selectedPartner,
  identityType
) {
  /*
   * Delivery Partner deliberately does NOT exist
   * inside parent_categories.
   */
  if (identityType === 'delivery_partner') {
    return 0
  }

  const id = Number(selectedPartner?.id)

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    throw new Error(
      'Invalid parent category selected.'
    )
  }

  return id
}


export const partnerAuthService = {

  /*
  |--------------------------------------------------------------------------
  | Send Firebase OTP
  |--------------------------------------------------------------------------
  */

  async requestOtp({
    countryCode = '+91',
    mobile,
  }) {
    return firebasePhoneAuthService.requestOtp({
      countryCode,
      mobile,
    })
  },


  /*
  |--------------------------------------------------------------------------
  | Verify OTP + Essivery Authentication
  |--------------------------------------------------------------------------
  */

  async verifyOtp({
    otp,
    selectedPartner,
  }) {

    /*
     * STEP 1
     *
     * Verify OTP with Firebase.
     */
    const firebaseCredential =
      await firebasePhoneAuthService.verifyOtp(
        otp
      )


    /*
     * STEP 2
     *
     * Determine the selected Essivery identity.
     */
    const identityType =
      normalizeIdentityType(
        selectedPartner
      )


    /*
     * STEP 3
     *
     * Determine parent category.
     *
     * Delivery Partner = 0
     */
    const parentCategoryId =
      getParentCategoryId(
        selectedPartner,
        identityType
      )


    /*
     * STEP 4
     *
     * Exchange Firebase verified token
     * with the EXISTING Essivery authentication API.
     *
     * Base URL:
     *
     * https://essivery.in/api/user
     *
     * Final request:
     *
     * POST /auth/firebase-phone
     */
    const response =
      await userApi.post(
        '/auth/firebase-phone',
        {
          idToken:
            firebaseCredential.idToken,

          identityType,

          parentCategoryId,
        }
      )


    /*
     * STEP 5
     *
     * Essivery API response normalization.
     */
    const payload =
      response.data?.data ??
      response.data


    /*
     * STEP 6
     *
     * Validate authentication response.
     */
    if (
      !payload?.tokens?.accessToken
    ) {
      throw new Error(
        'Authentication response was invalid.'
      )
    }


    /*
     * STEP 7
     *
     * Make sure backend returned the identity
     * we actually requested.
     */
    if (
      payload?.identity?.type &&
      payload.identity.type !== identityType
    ) {
      console.error(
        'Identity mismatch',
        {
          requested:
            identityType,

          returned:
            payload.identity.type,
        }
      )

      const identityError = new Error('Authenticated Partner identity did not match the selected Partner category.')
      identityError.code = 'PARTNER_IDENTITY_MISMATCH'
      clearAuthTokens()
      throw identityError
    }


    /*
     * STEP 8
     *
     * Save Essivery JWT tokens.
     */
    setAuthTokens({
      accessToken:
        payload.tokens.accessToken,

      refreshToken:
        payload.tokens.refreshToken,
    })

    const partnerState =
      await partnerContextService.loadOrBootstrapWithMeta()

    const partnerContext = partnerState.context

    if (partnerContext?.identity?.type !== identityType) {
      partnerContextService.clear()
      clearAuthTokens()
      const identityError = new Error(
        'Authenticated Partner identity did not match the selected Partner category.'
      )
      identityError.code = 'PARTNER_IDENTITY_MISMATCH'
      throw identityError
    }


    /*
     * STEP 9
     *
     * Return complete authentication result.
     */
    return {
      ...payload,

      selectedPartner,

      requestedIdentityType:
        identityType,

      parentCategoryId,

      partnerContext,

      isNewPartner: !partnerState.initialized,
    }
  },


  /*
  |--------------------------------------------------------------------------
  | Reset Firebase OTP Flow
  |--------------------------------------------------------------------------
  */

  reset() {
    firebasePhoneAuthService.reset()
    partnerContextService.clear()
  },
}
