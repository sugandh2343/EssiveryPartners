import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from 'react'

const PartnerSelectionContext = createContext(null)

export function PartnerSelectionProvider({ children }) {
  const [selectedPartner, setSelectedPartner] = useState(() => {
    try {
      const saved = sessionStorage.getItem(
        'essivery_selected_partner'
      )

      return saved ? JSON.parse(saved) : null
    } catch {
      return null
    }
  })

  useEffect(() => {
    if (selectedPartner) {
      sessionStorage.setItem(
        'essivery_selected_partner',
        JSON.stringify(selectedPartner)
      )
    } else {
      sessionStorage.removeItem(
        'essivery_selected_partner'
      )
    }
  }, [selectedPartner])

  const selectPartner = useCallback((partner) => {
    setSelectedPartner(partner)
  }, [])

  const clearPartner = useCallback(() => {
    setSelectedPartner(null)
  }, [])

  return (
    <PartnerSelectionContext.Provider
      value={{
        selectedPartner,
        selectPartner,
        clearPartner,
      }}
    >
      {children}
    </PartnerSelectionContext.Provider>
  )
}

export function usePartnerSelection() {
  const context = useContext(PartnerSelectionContext)

  if (!context) {
    throw new Error(
      'usePartnerSelection must be used inside PartnerSelectionProvider'
    )
  }

  return context
}
