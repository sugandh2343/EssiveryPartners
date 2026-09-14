# Essivery Partners frontend UI configuration

Last updated: 2026-09-15

## Integrated source

- GitHub source branch: `essiverypartners_pt`
- Imported branch head: `1f0b070` (`notification badge`)
- Integration target: `main`
- Scope: frontend-only dashboard, authentication presentation, partner cards, module placeholders, and dashboard service adapters.

## Frontend stack

- React 19 with Vite 8
- React Router 7
- Tailwind CSS 4 utility classes
- Axios through the shared `partnerApi` client
- Lucide React and React Icons for icons
- `react-otp-input` for the six-cell OTP control
- `react-loading-skeleton` for loading presentation

## Visual language

- Primary brand family: Tailwind `emerald`; strong surfaces use `emerald-900` or `emerald-950`.
- Page background: `#f4f7f4` on the dashboard and `slate-50` on authentication/selection screens.
- Content cards: white, `slate-200` border, `rounded-2xl` or `rounded-[2rem]`, restrained `shadow-sm`/`shadow-xl`.
- Typography: Inter/system UI from `frontend/src/index.css`; headings generally use `font-black` and tight tracking.
- Feedback colors: emerald for success, amber for attention, rose/red for errors, blue for review/information.
- Responsive content width: dashboard uses a centered `max-w-7xl` layout with mobile-first Tailwind breakpoints.

## Dashboard building blocks

- `DashboardPage.jsx`: composition, setup state, recent orders, status messages, welcome/order dialogs.
- `StoreStatusToggle.jsx`: online/offline control with confirmation before going offline.
- `NewOrdersSection.jsx`: new/recent order filters and empty/error/loading states.
- `InventoryAlerts.jsx`: inventory attention cards and empty/error/loading states.
- `PartnerWallet.jsx`: wallet summary and unavailable state.
- `QuickAction.jsx`: reusable dashboard action tile.
- `StatusBadge.jsx`: normalized order-status presentation.

## Routes added by the UI branch

- `/catalogue`
- `/inventory`
- `/orders`
- `/wallet`

These currently render `ModulePlaceholderPage` behind `ProtectedPartnerRoute`. Replace them with real modules without changing the established URLs.

## API and media contracts

- Partner API base: `VITE_API_BASE_URL`, defaulting to `https://essivery.in/api/partner`.
- User API base: `VITE_USER_API_BASE_URL`, defaulting to `https://essivery.in/api/user`.
- Admin API base: `VITE_ADMIN_API_BASE_URL`, defaulting to `https://essivery.in/api/admin`.
- Media base: `VITE_MEDIA_BASE_URL`, defaulting to `https://essivery.in/api`.
- Dashboard service endpoints introduced by this UI:
  - `GET /orders?scope=dashboard&limit=10`
  - `PUT /store/status` with `{ "isOnline": boolean }`
  - `GET /inventory/alerts`
  - `GET /wallet/summary`
- A `404` for orders or inventory produces an empty UI state. Never substitute fake production data.

## Non-negotiable integration rules

- Preserve `ProtectedPartnerRoute`, `PartnerSessionContext`, referral state, and onboarding setup behavior.
- Keep server calls on the shared API clients so bearer tokens, refresh behavior, headers, and error handling remain consistent.
- Never commit real secrets or deployment values to `.env.example`; only document variable names and safe placeholders.
- Uploaded documents and bank proofs remain private authenticated media. Do not replace their file endpoints with public URLs.
- Run `npm run lint`, `npm run build`, and `npm run test:onboarding-bugfix` before merging or deploying frontend changes.

## Known follow-up

- The production bundle currently emits a Vite warning because the main JavaScript chunk is over 500 kB. Functionality is unaffected, but route-level lazy loading should be introduced before the dashboard grows significantly.
