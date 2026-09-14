// import { useState } from 'react'
// import {
//   ArrowLeft,
//   ArrowRight,
//   Bike,
//   Smartphone,
//   Store,
// } from 'lucide-react'

// import { useNavigate } from 'react-router-dom'
// import { usePartnerSelection } from '../context/PartnerSelectionContext'


import {
  useState,
} from 'react'

import {
  ArrowLeft,
  ArrowRight,
  Bike,
  Loader2,
  Smartphone,
  Store,
} from 'lucide-react'

import {
  useNavigate,
} from 'react-router-dom'

import {
  usePartnerSelection,
} from '../context/PartnerSelectionContext'

import {
  partnerAuthService,
} from '../services/auth/partnerAuthService'
import { referralStateService } from '../services/referral/referralStateService'
import { usePartnerSession } from '../context/PartnerSessionContext'
import OtpInput from 'react-otp-input'

function LoginPage() {
  const navigate = useNavigate()
  const { accept } = usePartnerSession()

  const {
    selectedPartner,
    clearPartner,
  } = usePartnerSelection()

  const [mobile, setMobile] = useState('')


  const [step, setStep] =
  useState('mobile')



const [otp, setOtp] =
  useState('')

const [loading, setLoading] =
  useState(false)

const [error, setError] =
  useState('')

  // Never show login form without category selection
  if (!selectedPartner) {
    navigate('/choose-partner?action=login')
    return null
  }

  const changePartner = () => {
    clearPartner()
    navigate('/choose-partner?action=login')
  }

  const handleMobileSubmit =
  async (event) => {

    event.preventDefault()

    if (mobile.length !== 10) {
      return
    }

    try {
      setLoading(true)
      setError('')

      await partnerAuthService.requestOtp({
        countryCode: '+91',
        mobile,
      })

      setStep('otp')

    } catch (error) {

      console.error(
        'OTP request error:',
        error
      )

      let message =
        'Unable to send OTP. Please try again.'

      const firebaseFailure = `${error?.code || ''} ${error?.message || ''}`

      if (
        firebaseFailure.includes('requests-from-referer') ||
        firebaseFailure.includes('unauthorized-domain')
      ) {
        message =
          'OTP is temporarily unavailable because this Partner domain is not authorized.'
      }

      switch (error?.code) {

        case 'auth/too-many-requests':
          message =
            'Too many OTP requests. Please wait before trying again.'
          break

        case 'auth/invalid-phone-number':
          message =
            'Please enter a valid mobile number.'
          break

        case 'auth/captcha-check-failed':
          message =
            'Security verification failed. Please try again.'
          break

        case 'auth/quota-exceeded':
          message =
            'OTP service quota has been exceeded.'
          break
      }

      setError(message)

    } finally {
      setLoading(false)
    }
  }

  const handleOtpSubmit =
  async (event) => {

    event.preventDefault()

    if (otp.length !== 6) {
      return
    }

    try {

      setLoading(true)
      setError('')

      const result =
        await partnerAuthService.verifyOtp({
          otp,
          selectedPartner,
        })

      let referralContext = null
      try {
        referralContext = await referralStateService.completeAfterAuthentication()
      } catch (referralError) {
        const code = referralError?.response?.data?.error?.code
        if (!['REFERRAL_MODULE_MISMATCH', 'REFERRAL_NOT_APPLICABLE', 'REFERRAL_NOT_CLAIMABLE'].includes(code)) throw referralError
        referralContext = referralStateService.getSafeContext()
      }

      accept(result.partnerContext)
      navigate('/dashboard', {
        replace: true,
        state: {
          showWelcome: result.isNewPartner,
          referralLinked: referralContext?.linked === true,
          referralMessage: referralContext?.state === 'invalid' ? referralContext.message : '',
          businessName: referralContext?.prefill?.businessName || referralContext?.businessName || '',
        },
      })

    } catch (error) {

      if (error?.code === 'PARTNER_IDENTITY_MISMATCH') {
        clearPartner()
        navigate('/choose-partner?action=login', { replace: true })
        return
      }

      const backendMessage =
        error?.response?.data?.error?.message ||
        error?.response?.data?.message

      setError(
        backendMessage ||
        error?.message ||
        'OTP verification failed.'
      )

    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-slate-50">

      <div className="mx-auto grid min-h-screen max-w-7xl lg:grid-cols-2">

        {/* LEFT */}
        <div className="hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">

          <div className="text-2xl font-black">
            Essivery
            <span className="ml-2 text-sm font-semibold text-slate-400">
              PARTNERS
            </span>
          </div>

          <div>

            <h1 className="max-w-lg text-5xl font-black leading-tight">
              Grow your business with Essivery.
            </h1>

            <p className="mt-6 max-w-lg text-lg leading-8 text-slate-300">
              Manage orders, customers, services, catalogue and
              business growth from one partner platform.
            </p>

          </div>

          <p className="text-sm text-slate-500">
            Essentials. Instant. Delivery.
          </p>

        </div>


        {/* RIGHT */}
        <div className="flex items-center justify-center px-5 py-12">

          <div className="w-full max-w-md">

            <button
              onClick={() => navigate('/')}
              className="mb-8 flex items-center gap-2 text-sm font-bold text-slate-600"
            >
              <ArrowLeft size={17} />
              Back to home
            </button>


            <div className="rounded-[2rem] border border-slate-200 bg-white p-7 shadow-xl sm:p-9">

              <div className="mb-7">

                <p className="text-sm font-bold text-slate-400">
                  YOU ARE LOGGING IN AS
                </p>

                <div className="mt-3 flex items-center justify-between rounded-2xl bg-slate-50 p-4">

                  <div className="flex items-center gap-3">

                    <PartnerIcon
                      partner={selectedPartner}
                    />

                    <div>
                      <p className="text-lg font-black text-slate-950">
                        {selectedPartner.name}
                      </p>

                      <p className="text-xs font-semibold text-slate-500">
                        {selectedPartner.referralLocked
                          ? `Referred for ${selectedPartner.name}`
                          : 'Essivery Partner'}
                      </p>
                    </div>

                  </div>

                  {!selectedPartner.referralLocked && <button
                    onClick={changePartner}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 transition hover:bg-slate-100"
                  >
                    Change
                  </button>}

                </div>

              </div>


              <h1 className="text-3xl font-black tracking-tight text-slate-950">
                Welcome back
              </h1>

              <p className="mt-2 text-sm leading-6 text-slate-500">
                Enter your registered mobile number to continue.
              </p>


              {step === 'mobile' && (
  <form
    onSubmit={handleMobileSubmit}
    className="mt-8"
  >

    <label className="text-sm font-black text-slate-700">
      Mobile Number
    </label>

    <div className="mt-2 flex overflow-hidden rounded-2xl border border-slate-300 bg-white focus-within:border-slate-950">

      <div className="flex items-center border-r border-slate-200 bg-slate-50 px-4 font-bold">
        +91
      </div>

      <div className="flex flex-1 items-center">

        <Smartphone
          size={18}
          className="ml-4 text-slate-400"
        />

        <input
          type="tel"
          maxLength={10}
          value={mobile}
          onChange={(event) =>
            setMobile(
              event.target.value.replace(
                /\D/g,
                ''
              )
            )
          }
          placeholder="Enter 10 digit mobile number"
          className="w-full bg-transparent px-3 py-4 font-semibold outline-none"
        />

      </div>

    </div>

    {error && (
      <p className="mt-3 text-sm font-semibold text-red-600">
        {error}
      </p>
    )}

    <button
      disabled={
        mobile.length !== 10 ||
        loading
      }
      className="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-4 font-black text-white disabled:bg-slate-300"
    >

      {loading ? (
        <>
          <Loader2
            size={18}
            className="animate-spin"
          />
          Sending OTP...
        </>
      ) : (
        <>
          Continue
          <ArrowRight size={18} />
        </>
      )}

    </button>

  </form>
)}

{step === 'otp' && (

  <form
    onSubmit={handleOtpSubmit}
    className="mt-8"
  >

    <div className="mb-6 rounded-2xl bg-emerald-50 p-4">

      <p className="text-sm font-bold text-emerald-800">
        OTP sent to +91 ******{mobile.slice(-4)}
      </p>

    </div>


    <label className="text-sm font-black text-slate-700">
      Verification Code
    </label>


    <div className="mt-2 px-1 py-1">
      <OtpInput
        value={otp}
        onChange={(value) => setOtp(value?.replace(/\D/g, ''))}
        numInputs={6}
        shouldAutoFocus
        inputType="tel"
        containerStyle="flex w-full justify-between gap-2"
        renderInput={(props) => (
          <input
            {...props}
            inputMode="numeric"
            autoComplete="one-time-code"
            className="h-12 min-w-0 flex-1 rounded-xl border border-slate-300 bg-slate-50 text-center text-xl font-black text-slate-950 outline-none transition focus:border-slate-950 focus:bg-white"
          />
        )}
      />
    </div>


    {error && (
      <p className="mt-3 text-sm font-semibold text-red-600">
        {error}
      </p>
    )}


    <button
      disabled={
        otp.length !== 6 ||
        loading
      }
      className="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-4 font-black text-white disabled:bg-slate-300"
    >

      {loading ? (
        <>
          <Loader2
            size={18}
            className="animate-spin"
          />

          Verifying...
        </>
      ) : (
        <>
          Verify & Login
          <ArrowRight size={18} />
        </>
      )}

    </button>


    <button
      type="button"
      onClick={() => {
        partnerAuthService.reset()
        setOtp('')
        setError('')
        setStep('mobile')
      }}
      className="mt-4 w-full text-sm font-black text-slate-600"
    >
      Change Mobile Number
    </button>

  </form>

)}

            </div>

          </div>

        </div>

      </div>

      <div
  id="firebase-phone-recaptcha"
/>

    </div>
  )
}

function PartnerIcon({ partner }) {
  if (partner.type === 'delivery') {
    return (
      <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-950 text-white">
        <Bike size={22} />
      </div>
    )
  }

  if (partner.image) {
    return (
      <img
        src={partner.image}
        alt={partner.name}
        className="h-12 w-12 rounded-xl bg-slate-100 object-contain p-1"
        onError={(event) => {
          event.currentTarget.style.display = 'none'
        }}
      />
    )
  }

  return (
    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-950 text-white">
      <Store size={22} />
    </div>
  )
}

export default LoginPage
