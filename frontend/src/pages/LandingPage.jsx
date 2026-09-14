import { useEffect, useState } from 'react'
import {
  ArrowRight,
  BadgeCheck,
  Bike,
  Loader2,
  ShieldCheck,
  Sparkles,
  Store,
  TrendingUp,
} from 'lucide-react'

import { partnerApi } from '../api/client'

import { useNavigate } from 'react-router-dom'
import { usePartnerSelection } from '../context/PartnerSelectionContext'

function LandingPage() {
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const navigate = useNavigate()

const { selectPartner } =
  usePartnerSelection()

  useEffect(() => {
    fetchCategories()
  }, [])

  const fetchCategories = async () => {
    try {
      setLoading(true)
      setError('')

      const response = await partnerApi.get('/catalogue/parent-categories')

      if (response.data.success) {
        setCategories(response.data.data || [])
      } else {
        setError('Unable to load partner categories.')
      }
    } catch {
      setError('Unable to connect with Essivery.')
    } finally {
      setLoading(false)
    }
  }

 const handleCategoryClick = (category) => {
  selectPartner({
    id: category.id,
    public_id: category.public_id,
    name: category.name,
    slug: category.slug,
    image: category.image,
    type: 'business',
  })

  navigate('/login')
}

  const handleDeliveryClick = () => {
  selectPartner({
    id: null,
    public_id: null,
    name: 'Delivery Partner',
    slug: 'delivery-partner',
    image: null,
    type: 'delivery',
  })

  navigate('/login')
}

  return (
    <div className="min-h-screen bg-slate-50">

      {/* NAVBAR */}
      <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">

          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-lg">
              <Store size={22} />
            </div>

            <div>
              <div className="text-xl font-black tracking-tight text-slate-950">
                Essivery
              </div>

              <div className="-mt-1 text-xs font-semibold tracking-wide text-slate-500">
                PARTNERS
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button
  onClick={() => {
    window.location.href =
      '/choose-partner?action=login'
  }}
  className="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-lg"
>
  Partner Login
</button>

           

          </div>
        </div>
      </header>


      {/* HERO */}
      <section className="relative overflow-hidden">

        <div className="absolute -left-40 top-20 h-96 w-96 rounded-full bg-orange-200/40 blur-3xl" />

        <div className="absolute -right-40 top-10 h-96 w-96 rounded-full bg-emerald-200/40 blur-3xl" />

        <div className="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-20 lg:grid-cols-2 lg:px-8 lg:py-28">

          <div>

            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm">

              <Sparkles size={16} />

              Grow your business with Essivery
            </div>

            <h1 className="max-w-3xl text-5xl font-black leading-[1.05] tracking-tight text-slate-950 md:text-6xl">

              Your Business.

              <span className="block bg-gradient-to-r from-orange-500 via-slate-800 to-emerald-600 bg-clip-text text-transparent">
                Bigger Opportunities.
              </span>

            </h1>

            <p className="mt-7 max-w-xl text-lg leading-8 text-slate-600">
              Take your local business online, reach nearby customers,
              receive orders digitally and grow with a platform built
              for India's local businesses.
            </p>

            <div className="mt-9 flex flex-wrap gap-4">

             <button
  onClick={() => {
    window.location.href =
      '/choose-partner?action=login'
  }}
  className="inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-7 py-4 font-bold text-white shadow-xl"
>
  Get Started
  <ArrowRight size={19} />
</button>

             

            </div>


            <div className="mt-10 flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-slate-600">

              <span className="flex items-center gap-2">
                <BadgeCheck size={18} className="text-emerald-600" />
                Simple Registration
              </span>

              <span className="flex items-center gap-2">
                <TrendingUp size={18} className="text-emerald-600" />
                Grow Locally
              </span>

              <span className="flex items-center gap-2">
                <ShieldCheck size={18} className="text-emerald-600" />
                Secure Platform
              </span>

            </div>

          </div>


          {/* HERO CARD */}
          <div className="relative">

            <div className="absolute inset-5 rotate-3 rounded-[2.5rem] bg-gradient-to-br from-orange-200 to-emerald-200" />

            <div className="relative rounded-[2.5rem] border border-white/80 bg-white/90 p-8 shadow-2xl backdrop-blur-xl">

              <div className="mb-8 flex items-center justify-between">

                <div>
                  <p className="text-sm font-bold text-slate-400">
                    ESSIVERY PARTNERS
                  </p>

                  <h2 className="mt-1 text-2xl font-black text-slate-950">
                    Everything you need to grow
                  </h2>
                </div>

                <div className="rounded-2xl bg-emerald-50 p-4 text-emerald-600">
                  <TrendingUp size={28} />
                </div>

              </div>

              <div className="space-y-4">

                <Feature
                  title="Reach nearby customers"
                  description="Make your business discoverable across your service area."
                />

                <Feature
                  title="Manage everything digitally"
                  description="Orders, catalogue, inventory, services and business settings."
                />

                <Feature
                  title="Built for local businesses"
                  description="From grocery stores to restaurants, service providers and delivery partners."
                />

              </div>

            </div>
          </div>

        </div>
      </section>


      {/* BUSINESS CATEGORY SECTION */}
      <section
        id="categories"
        className="mx-auto max-w-7xl px-5 py-20 lg:px-8"
      >

        <div className="mx-auto mb-12 max-w-3xl text-center">

          <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-600">
            Become an Essivery Partner
          </span>

          <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950 md:text-5xl">
            What type of business do you run?
          </h2>

          <p className="mt-5 text-lg leading-8 text-slate-600">
            Choose your business category and start your Essivery
            partner registration.
          </p>

        </div>


        {/* LOADING */}
        {loading && (
          <div className="flex min-h-60 items-center justify-center">

            <div className="text-center">
              <Loader2
                className="mx-auto animate-spin text-slate-700"
                size={34}
              />

              <p className="mt-3 text-sm font-semibold text-slate-500">
                Loading business categories...
              </p>
            </div>

          </div>
        )}


        {/* ERROR */}
        {!loading && error && (
          <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">

            <p className="font-semibold text-red-700">
              {error}
            </p>

            <button
              onClick={fetchCategories}
              className="mt-4 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white"
            >
              Try Again
            </button>

          </div>
        )}


        {/* CATEGORY GRID */}
        {!loading && !error && (

          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

            {categories.map((category) => (

              <CategoryCard
                key={category.id}
                category={category}
                onClick={() => handleCategoryClick(category)}
              />

            ))}


            {/* DELIVERY PARTNER */}
            <button
              onClick={handleDeliveryClick}
              className="group overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white text-left shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-slate-300 hover:shadow-2xl"
            >

              <div className="relative flex h-48 items-center justify-center overflow-hidden bg-gradient-to-br from-slate-950 via-slate-800 to-emerald-800">

                <div className="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10" />

                <div className="absolute -bottom-14 -left-12 h-40 w-40 rounded-full bg-emerald-400/10" />

                <Bike
                  size={76}
                  strokeWidth={1.4}
                  className="relative text-white"
                />

              </div>


              <div className="p-6">

                <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-emerald-700">
                  Earn with Essivery
                </span>

                <h3 className="mt-4 text-xl font-black text-slate-950">
                  Delivery Partner
                </h3>

                <p className="mt-2 text-sm leading-6 text-slate-500">
                  Deliver orders around your city and earn with
                  flexible working opportunities.
                </p>

                <div className="mt-5 flex items-center gap-2 text-sm font-black text-slate-900">
                  Continue

                  <ArrowRight
                    size={17}
                    className="transition group-hover:translate-x-1"
                  />
                </div>

              </div>

            </button>

          </div>

        )}

      </section>


      {/* CTA */}
      <section className="mx-auto max-w-7xl px-5 pb-20 lg:px-8">

        <div className="overflow-hidden rounded-[2.5rem] bg-slate-950 px-8 py-14 text-center text-white shadow-2xl md:px-16">

          <h2 className="text-3xl font-black md:text-4xl">
            Ready to grow with Essivery?
          </h2>

          <p className="mx-auto mt-4 max-w-2xl text-slate-300">
            Register your business and become part of Essivery's
            growing local commerce ecosystem.
          </p>

          <a
            href="#categories"
            className="mt-8 inline-flex items-center gap-2 rounded-2xl bg-white px-7 py-4 font-black text-slate-950 transition hover:scale-105"
          >
            Get Started

            <ArrowRight size={18} />
          </a>

        </div>

      </section>


      {/* FOOTER */}
      <footer className="border-t border-slate-200 bg-white">

        <div className="mx-auto flex max-w-7xl flex-col justify-between gap-4 px-5 py-8 text-sm text-slate-500 sm:flex-row lg:px-8">

          <p>
            © {new Date().getFullYear()} Essivery. All rights reserved.
          </p>

          <p className="font-semibold">
            Essentials. Instant. Delivery.
          </p>

        </div>

      </footer>

    </div>
  )
}


function Feature({ title, description }) {
  return (
    <div className="flex gap-4 rounded-2xl bg-slate-50 p-5">

      <div className="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
        <BadgeCheck size={19} />
      </div>

      <div>
        <h3 className="font-black text-slate-900">
          {title}
        </h3>

        <p className="mt-1 text-sm leading-6 text-slate-500">
          {description}
        </p>
      </div>

    </div>
  )
}


function CategoryCard({ category, onClick }) {

  const defaultImage = '/default-partner.svg'

  return (
    <button
      onClick={onClick}
      className="group overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white text-left shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-slate-300 hover:shadow-2xl"
    >

      <div className="h-48 overflow-hidden bg-slate-100">

       <img
  src={
    category.image
      ? `https://essivery.in/api/${category.image.replace(/^\/+/, '')}`
      : defaultImage
  }
  alt={category.name}
  onError={(event) => {
    event.currentTarget.onerror = null
    event.currentTarget.src = defaultImage
  }}
  className="h-full w-full object-contain transition duration-500 group-hover:scale-110"
/>
      </div>


      <div className="p-6">

        <h3 className="text-xl font-black text-slate-950">
          {category.name}
        </h3>

        <p className="mt-2 text-sm leading-6 text-slate-500">
          Register your {category.name.toLowerCase()} business with
          Essivery and start reaching nearby customers.
        </p>

        <div className="mt-5 flex items-center gap-2 text-sm font-black text-slate-900">

          Continue

          <ArrowRight
            size={17}
            className="transition group-hover:translate-x-1"
          />

        </div>

      </div>

    </button>
  )
}

export default LandingPage
