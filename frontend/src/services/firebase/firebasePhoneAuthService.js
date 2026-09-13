import {
  RecaptchaVerifier,
  signInWithPhoneNumber,
} from 'firebase/auth'

import { firebaseAuth } from './firebase'

let recaptchaVerifier = null
let confirmationResult = null

function resetRecaptcha() {
  if (recaptchaVerifier) {
    try {
      recaptchaVerifier.clear()
    } catch {
      // ignore cleanup errors
    }
  }

  recaptchaVerifier = null
}

function createRecaptchaVerifier() {
  resetRecaptcha()

  const element = document.getElementById(
    'firebase-phone-recaptcha'
  )

  if (!element) {
    throw new Error(
      'Firebase reCAPTCHA container was not found.'
    )
  }

  recaptchaVerifier = new RecaptchaVerifier(
    firebaseAuth,
    'firebase-phone-recaptcha',
    {
      size: 'invisible',

      callback: () => {},

      'expired-callback': () => {
        resetRecaptcha()
      },
    }
  )

  return recaptchaVerifier
}

export const firebasePhoneAuthService = {
  async requestOtp({
    countryCode = '+91',
    mobile,
  }) {
    const phoneNumber = `${countryCode}${mobile}`

    const verifier =
      createRecaptchaVerifier()

    try {
      confirmationResult =
        await signInWithPhoneNumber(
          firebaseAuth,
          phoneNumber,
          verifier
        )

      return {
        success: true,
        phoneNumber,
      }

    } catch (error) {
      resetRecaptcha()
      confirmationResult = null
      throw error
    }
  },

  async verifyOtp(otp) {
    if (!confirmationResult) {
      throw new Error(
        'OTP session has expired. Please request OTP again.'
      )
    }

    const credential =
      await confirmationResult.confirm(otp)

    const idToken =
      await credential.user.getIdToken()

    return {
      idToken,
      firebaseUser: credential.user,
    }
  },

  reset() {
    confirmationResult = null
    resetRecaptcha()
  },
}
