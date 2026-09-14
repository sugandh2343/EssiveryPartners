import {
  BrowserRouter,
  Routes,
  Route,
} from 'react-router-dom'

import LandingPage from './pages/LandingPage'
import ChoosePartnerPage from './pages/ChoosePartnerPage'
import LoginPage from './pages/LoginPage'
import ReferralNotice from './components/ReferralNotice'
import DashboardPage from './pages/DashboardPage'
import ProtectedPartnerRoute from './components/ProtectedPartnerRoute'
import SetupCenterPage from './pages/SetupCenterPage'
import SetupPlaceholderPage from './pages/SetupPlaceholderPage'
import PersonalDetailsPage from './pages/PersonalDetailsPage'
import BusinessDetailsPage from './pages/BusinessDetailsPage'
import LocationDetailsPage from './pages/LocationDetailsPage'
import BusinessHoursPage from './pages/BusinessHoursPage'
import OperationsPage from './pages/OperationsPage'
import DocumentsPage from './pages/DocumentsPage'
import BankDetailsPage from './pages/BankDetailsPage'
import RetailSetupPage from './pages/RetailSetupPage'
import RestaurantSetupPage from './pages/RestaurantSetupPage'
import HomeServiceSetupPage from './pages/HomeServiceSetupPage'
import DeliverySetupPage from './pages/DeliverySetupPage'
import ReviewSubmitPage from './pages/ReviewSubmitPage'
import ReferralRegistrationPage from './pages/ReferralRegistrationPage'
import ModulePlaceholderPage from './pages/ModulePlaceholderPage'

function App() {
  return (
    <BrowserRouter>

      <ReferralNotice />

      <Routes>

        <Route
          path="/"
          element={<LandingPage />}
        />

        <Route
          path="/choose-partner"
          element={<ChoosePartnerPage />}
        />

        <Route
          path="/register"
          element={<ReferralRegistrationPage />}
        />

        <Route
          path="/login"
          element={<LoginPage />}
        />

        <Route path="/dashboard" element={<ProtectedPartnerRoute><DashboardPage /></ProtectedPartnerRoute>} />
        <Route path="/catalogue" element={<ProtectedPartnerRoute><ModulePlaceholderPage /></ProtectedPartnerRoute>} />
        <Route path="/inventory" element={<ProtectedPartnerRoute><ModulePlaceholderPage /></ProtectedPartnerRoute>} />
        <Route path="/orders" element={<ProtectedPartnerRoute><ModulePlaceholderPage /></ProtectedPartnerRoute>} />
        <Route path="/wallet" element={<ProtectedPartnerRoute><ModulePlaceholderPage /></ProtectedPartnerRoute>} />
        <Route path="/setup" element={<ProtectedPartnerRoute><SetupCenterPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/personal" element={<ProtectedPartnerRoute><PersonalDetailsPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/business" element={<ProtectedPartnerRoute><BusinessDetailsPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/location" element={<ProtectedPartnerRoute><LocationDetailsPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/hours" element={<ProtectedPartnerRoute><BusinessHoursPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/operations" element={<ProtectedPartnerRoute><OperationsPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/documents" element={<ProtectedPartnerRoute><DocumentsPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/bank" element={<ProtectedPartnerRoute><BankDetailsPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/retail" element={<ProtectedPartnerRoute><RetailSetupPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/restaurant" element={<ProtectedPartnerRoute><RestaurantSetupPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/home-service" element={<ProtectedPartnerRoute><HomeServiceSetupPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/delivery" element={<ProtectedPartnerRoute><DeliverySetupPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/review" element={<ProtectedPartnerRoute><ReviewSubmitPage /></ProtectedPartnerRoute>} />
        <Route path="/setup/:step" element={<ProtectedPartnerRoute><SetupPlaceholderPage /></ProtectedPartnerRoute>} />

      </Routes>

    </BrowserRouter>
  )
}

export default App
