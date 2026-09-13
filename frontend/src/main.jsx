import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'

import { PartnerSelectionProvider } from './context/PartnerSelectionContext.jsx'
import { PartnerSessionProvider } from './context/PartnerSessionContext.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <PartnerSelectionProvider>
      <PartnerSessionProvider><App /></PartnerSessionProvider>
    </PartnerSelectionProvider>
  </StrictMode>
)
