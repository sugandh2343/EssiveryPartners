import { useEffect, useState } from 'react'
import {
  ArrowLeft,
  ArrowRight,
  Bike,
  Loader2,
} from 'lucide-react'

import { useNavigate } from 'react-router-dom'
import { partnerApi } from '../api/client'
import { usePartnerSelection } from '../context/PartnerSelectionContext'

function ChoosePartnerPage() {
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const navigate = useNavigate()
  const { selectPartner } = usePartnerSelection()

  useEffect(() => {
    fetchCategories()
  }, [])

  const fetchCategories = async () => {
    try {
      setLoading(true)

      const response = await partnerApi.get(
        '/catalogue/parent-categories'
      )

      if (response.data.success) {
        setCategories(response.data.data || [])
      } else {
        setError('Unable to load partner categories.')
      }
    } catch {
      setError('Unable to load partner categories.')
    } finally {
      setLoading(false)
    }
  }

  const chooseCategory = (category) => {
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

  const chooseDelivery = () => {
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

      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center px-5 py-5 lg:px-8">

          <button
            onClick={() => navigate('/')}
            className="flex items-center gap-2 font-bold text-slate-700"
          >
            <ArrowLeft size={18} />
            Back
          </button>

        </div>
      </header>


      <main className="mx-auto max-w-7xl px-5 py-14 lg:px-8">

        <div className="mx-auto mb-12 max-w-2xl text-center">

          <span className="text-sm font-black uppercase tracking-[0.2em] text-emerald-600">
            Essivery Partners
          </span>

          <h1 className="mt-4 text-4xl font-black tracking-tight text-slate-950">
            Choose your partner type
          </h1>

          <p className="mt-4 text-lg text-slate-600">
            Select the business or partner category you want to{' '}
            continue with.
          </p>

        </div>


        {loading && (
          <div className="flex justify-center py-20">
            <Loader2
              className="animate-spin text-slate-700"
              size={34}
            />
          </div>
        )}


        {!loading && error && (
          <div className="mx-auto max-w-xl rounded-2xl border border-red-200 bg-red-50 p-5 text-center font-semibold text-red-700">
            {error}
          </div>
        )}


        {!loading && !error && (
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

            {categories.map((category) => (
              <PartnerChoiceCard
                key={category.id}
                name={category.name}
                image={category.image}
                onClick={() => chooseCategory(category)}
              />
            ))}


            <button
              onClick={chooseDelivery}
              className="group overflow-hidden rounded-3xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
            >

              <div className="flex h-36 items-center justify-center bg-slate-950 text-white">
                <Bike size={58} strokeWidth={1.5} />
              </div>

              <div className="p-5">

                <h2 className="text-lg font-black text-slate-950">
                  Delivery Partner
                </h2>

                <div className="mt-4 flex items-center gap-2 text-sm font-black">
                  Select
                  <ArrowRight
                    size={16}
                    className="transition group-hover:translate-x-1"
                  />
                </div>

              </div>

            </button>

          </div>
        )}

      </main>

    </div>
  )
}

function PartnerChoiceCard({
  name,
  image,
  onClick,
}) {
  const defaultImage = '/default-partner.svg'

  return (
    <button
      onClick={onClick}
      className="group overflow-hidden rounded-3xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
    >

      <div className="h-36 overflow-hidden bg-slate-100">

        <img
          src={image || defaultImage}
          alt={name}
          onError={(event) => {
            event.currentTarget.onerror = null
            event.currentTarget.src = defaultImage
          }}
          className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
        />

      </div>

      <div className="p-5">

        <h2 className="text-lg font-black text-slate-950">
          {name}
        </h2>

        <div className="mt-4 flex items-center gap-2 text-sm font-black">
          Select
          <ArrowRight
            size={16}
            className="transition group-hover:translate-x-1"
          />
        </div>

      </div>

    </button>
  )
}

export default ChoosePartnerPage
