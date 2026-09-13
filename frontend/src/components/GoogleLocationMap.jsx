import { useEffect, useRef, useState } from 'react'
import { googleMapsConfig } from '../config/googleMaps'

let loader

function loadMaps() {
  if (window.google?.maps) return Promise.resolve(window.google.maps)
  if (!googleMapsConfig.apiKey) return Promise.reject(new Error('MAPS_NOT_CONFIGURED'))
  if (loader) return loader

  loader = new Promise((resolve, reject) => {
    const callback = `essiveryMaps_${Date.now()}`
    const previousAuthFailure = window.gm_authFailure
    const script = document.createElement('script')
    const finish = (error) => {
      clearTimeout(timeout)
      delete window[callback]
      window.gm_authFailure = previousAuthFailure
      if (error) {
        script.remove()
        loader = undefined
        reject(error)
      } else resolve(window.google.maps)
    }
    const timeout = setTimeout(() => finish(new Error('MAPS_LOAD_TIMEOUT')), 15000)

    window.gm_authFailure = () => finish(new Error('MAPS_AUTH_FAILED'))
    window[callback] = () => window.google?.maps ? finish() : finish(new Error('MAPS_LOAD_FAILED'))
    const params = new URLSearchParams({
      key: googleMapsConfig.apiKey,
      callback,
      loading: 'async',
      v: 'weekly',
      region: googleMapsConfig.region,
      language: googleMapsConfig.language,
      auth_referrer_policy: 'origin',
    })
    script.src = `https://maps.googleapis.com/maps/api/js?${params}`
    script.async = true
    script.onerror = () => finish(new Error('MAPS_LOAD_FAILED'))
    document.head.appendChild(script)
  })
  return loader
}

function components(result) {
  const find = type => result.address_components?.find(item => item.types.includes(type))?.long_name || ''
  return {
    addressLine1: [find('street_number'), find('route')].filter(Boolean).join(' '),
    locality: find('sublocality_level_1') || find('sublocality') || find('neighborhood'),
    city: find('locality') || find('administrative_area_level_3'),
    state: find('administrative_area_level_1'),
    pincode: find('postal_code'),
  }
}

function errorMessage(code) {
  if (code === 'MAPS_NOT_CONFIGURED') return 'Google Maps is not configured in this frontend build.'
  if (code === 'MAPS_AUTH_FAILED') return 'Google Maps rejected the API key or this website origin.'
  if (code === 'MAPS_LOAD_TIMEOUT') return 'Google Maps took too long to respond.'
  return 'Google Maps could not be loaded. Check the network and API configuration.'
}

export default function GoogleLocationMap({ latitude, longitude, onMove, onSuggestion }) {
  const element = useRef(null)
  const mapRef = useRef()
  const markerRef = useRef()
  const moveRef = useRef(onMove)
  const suggestionRef = useRef(onSuggestion)
  const initial = useRef({ latitude, longitude })
  const [state, setState] = useState('loading')
  const [failure, setFailure] = useState('')
  const [attempt, setAttempt] = useState(0)

  useEffect(() => { moveRef.current = onMove; suggestionRef.current = onSuggestion }, [onMove, onSuggestion])
  useEffect(() => {
    let active = true
    setState('loading')
    setFailure('')
    loadMaps().then(maps => {
      if (!active) return
      const first = initial.current
      const center = { lat: Number(first.latitude) || 26.8467, lng: Number(first.longitude) || 80.9462 }
      const map = new maps.Map(element.current, { center, zoom: first.latitude && first.longitude ? 16 : 11, mapId: googleMapsConfig.mapId || undefined, streetViewControl: false, mapTypeControl: false })
      const marker = new maps.Marker({ map, position: center, draggable: true })
      const changed = position => {
        const point = { lat: position.lat().toFixed(7), lng: position.lng().toFixed(7) }
        moveRef.current(point)
        new maps.Geocoder().geocode({ location: { lat: Number(point.lat), lng: Number(point.lng) } }, (results, status) => {
          if (status === 'OK' && results?.[0]) suggestionRef.current({ ...components(results[0]), formattedAddress: results[0].formatted_address })
        })
      }
      marker.addListener('dragend', () => changed(marker.getPosition()))
      map.addListener('click', event => { marker.setPosition(event.latLng); changed(event.latLng) })
      mapRef.current = map
      markerRef.current = marker
      setState('ready')
    }).catch(error => {
      if (!active) return
      setFailure(error?.message || 'MAPS_LOAD_FAILED')
      setState('unavailable')
    })
    return () => { active = false }
  }, [attempt])

  useEffect(() => {
    if (!mapRef.current || !markerRef.current || !latitude || !longitude) return
    const point = { lat: Number(latitude), lng: Number(longitude) }
    if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return
    markerRef.current.setPosition(point)
    mapRef.current.panTo(point)
  }, [latitude, longitude])

  if (state === 'unavailable') return <div className="grid min-h-56 place-content-center rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-5 text-center text-sm text-amber-950"><strong>Map unavailable</strong><span>{errorMessage(failure)}</span><span className="mt-1 text-xs">You can still enter coordinates manually.</span>{failure !== 'MAPS_NOT_CONFIGURED' && <button type="button" onClick={() => setAttempt(value => value + 1)} className="mx-auto mt-3 rounded-lg border border-amber-700 px-4 py-2 font-black">Retry map</button>}</div>
  return <div className="relative"><div ref={element} className="min-h-72 rounded-2xl bg-slate-100" />{state === 'loading' && <span className="absolute inset-0 grid place-items-center rounded-2xl bg-slate-100 text-sm font-bold">Loading map…</span>}</div>
}
