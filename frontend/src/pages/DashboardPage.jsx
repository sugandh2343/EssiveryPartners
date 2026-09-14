import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import Skeleton from "react-loading-skeleton";
import "react-loading-skeleton/dist/skeleton.css";
import {
  Bell,
  Box,
  CalendarDays,
  Check,
  ChevronRight,
  Clock3,
  IndianRupee,
  LogOut,
  Menu,
  PackagePlus,
  Settings,
  Store,
  UserRound,
} from "lucide-react";
import {
  FaBoxOpen,
  FaCheckCircle,
  FaExclamationTriangle,
  FaClock,
  FaArrowRight,
  FaRupeeSign,
  FaShoppingBag,
} from "react-icons/fa";
import { usePartnerSession } from "../context/PartnerSessionContext";
import { usePartnerSelection } from "../context/PartnerSelectionContext";
import StoreStatusToggle from "../components/StoreStatusToggle";
import NewOrdersSection from "../components/NewOrdersSection";
import StatusBadge from "../components/StatusBadge";
import PartnerWallet from "../components/PartnerWallet";
import InventoryAlerts from "../components/InventoryAlerts";
import { dashboardService } from "../services/partner/dashboardService";
import { partnerSetupService } from "../services/partner/partnerSetupService";

const actionMap = {
  RETAIL: [
    ["Add Products", PackagePlus],
    ["Manage Inventory", Box],
    ["Orders", Menu],
    ["Business Profile", Store],
  ],
  RESTAURANT: [
    ["Menu", Menu],
    ["Orders", Box],
    ["Restaurant Profile", Store],
    ["Business Hours", Clock3],
  ],
  HOME_SERVICE: [
    ["Services", Settings],
    ["Bookings", CalendarDays],
    ["Availability", Clock3],
    ["Profile", UserRound],
  ],
  DELIVERY: [
    ["Go Online", Store],
    ["Assignments", Box],
    ["Earnings", IndianRupee],
    ["Profile", UserRound],
  ],
};

function statusCopy(context, setup) {
  const approval = String(
    context?.partner?.approvalStatus || "pending",
  ).toLowerCase();
  const coreSetup = String(
    context?.partner?.onboardingStatus || "draft",
  ).toLowerCase();
  if (approval === "suspended")
    return [
      "Account restricted",
      "Your Partner account is currently restricted. Contact Essivery support for assistance.",
      "rose",
    ];
  if (["rejected", "correction_required", "correction"].includes(approval))
    return [
      "Correction required",
      "Review the requested corrections before submitting your business again.",
      "amber",
    ];
  if (approval === "approved")
    return [
      "Approved Partner",
      coreSetup === "completed"
        ? "Your business is approved and ready for eligible Partner features."
        : "Your business is approved. Complete the remaining setup when convenient.",
      "emerald",
    ];
  if (["submitted", "resubmitted", "underReview"].includes(setup?.status))
    return [
      "Under Review",
      "Your Partner profile has been submitted for Essivery review.",
      "blue",
    ];
  if (setup?.status === "correctionRequired")
    return [
      "Action required",
      "Some Business Setup information needs to be updated.",
      "amber",
    ];
  if (
    ["submitted", "pending_review", "pending"].includes(approval) &&
    coreSetup !== "draft"
  )
    return [
      "Pending review",
      "Your application status is controlled by Essivery review.",
      "blue",
    ];
  return [
    "Setup incomplete",
    "Complete your business setup before submitting it for review.",
    "amber",
  ];
}

export default function DashboardPage() {
  const { context, logout } = usePartnerSession();
  const { selectedPartner, clearPartner } = usePartnerSelection();
  const location = useLocation();
  const navigate = useNavigate();
  const [setup, setSetup] = useState(partnerSetupService.getCached());
  const [setupError, setSetupError] = useState("");
  const [orders, setOrders] = useState([]);
  const [ordersLoading, setOrdersLoading] = useState(true);
  const [ordersError, setOrdersError] = useState("");
  const [orderFilter, setOrderFilter] = useState("new");
  const [ordersReload, setOrdersReload] = useState(0);
  const [initialLoading, setInitialLoading] = useState(true);
  const [orderDialog, setOrderDialog] = useState(null);
  const [notificationCount] = useState(0);
  const [storeOnline, setStoreOnline] = useState(() =>
    ["online", "open", "accepting_orders"].includes(
      String(context?.partner?.operationalStatus || "").toLowerCase(),
    ),
  );
  const welcomeKey = `essivery_partner_welcome_${context.identity.publicId}`;
  const [welcomeOpen, setWelcomeOpen] = useState(
    () =>
      Boolean(location.state?.showWelcome) &&
      localStorage.getItem(welcomeKey) !== "shown",
  );
  const referralLinked = Boolean(location.state?.referralLinked);
  const referralMessage = location.state?.referralMessage || "";
  const module = context.identity.module;
  const category =
    selectedPartner?.name ||
    context.identity.type?.replaceAll("_", " ") ||
    "Partner";
  const businessName = location.state?.businessName || `${category} Business`;
  const hour = new Date().getHours();
  const greeting =
    hour < 12 ? "Good Morning" : hour < 17 ? "Good Afternoon" : "Good Evening";
  const [statusTitle, statusMessage, statusTone] = statusCopy(context, setup);
  const actions = actionMap[module] || actionMap.RETAIL;
  const setupCompleteTitle =
    module === "DELIVERY"
      ? "Delivery Partner setup information is complete"
      : "Business setup information is complete";
  const setupCompleteMessage =
    module === "DELIVERY"
      ? "Your information is ready to review. This does not mean you are approved, online, or available for delivery jobs."
      : "Your information is ready to review. This does not mean your store is approved or live.";
  const pendingOrders = orders.filter((order) =>
    ["new", "pending", "placed"].includes(
      String(order.status || "").toLowerCase(),
    ),
  );
  useEffect(() => {
    const timer = window.setTimeout(() => setInitialLoading(false), 1000);
    return () => window.clearTimeout(timer);
  }, []);

  useEffect(() => {
    partnerSetupService
      .loadOrBootstrap()
      .then(setSetup)
      .catch((error) =>
        setSetupError(
          error?.response?.data?.error?.userMessage ||
            "Business Setup progress is temporarily unavailable.",
        ),
      );
  }, []);

  useEffect(() => {
    let active = true;
    setOrdersLoading(true);
    setOrdersError("");
    dashboardService
      .getRecentOrders()
      .then((result) => {
        if (!active) return;
        setOrders(
          Array.isArray(result) ? result : result?.orders || result?.data || [],
        );
      })
      .catch((error) => {
        if (!active) return;
        setOrdersError(
          error?.response?.data?.error?.userMessage ||
            "Recent orders could not be loaded.",
        );
      })
      .finally(() => {
        if (active) setOrdersLoading(false);
      });
    return () => {
      active = false;
    };
  }, [ordersReload]);

  const closeWelcome = () => {
    localStorage.setItem(welcomeKey, "shown");
    setWelcomeOpen(false);
    navigate("/dashboard", { replace: true });
  };
  const signOut = () => {
    logout();
    clearPartner();
    navigate("/", { replace: true });
  };
  if (initialLoading) return <DashboardSkeleton />;

  return (
    <div className="min-h-screen bg-[#f4f7f4] text-slate-950">
      <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
          <div className="flex items-center gap-3">
            <div className="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-900 text-white">
              <Store size={22} />
            </div>
            <div>
              <p className="text-lg font-black">Essivery</p>
              <p className="text-[10px] font-black tracking-[.2em] text-emerald-700">
                PARTNERS
              </p>
            </div>
          </div>
          <div className="hidden text-right sm:block">
            <p className="text-sm font-black capitalize">{businessName}</p>
            <p className="text-xs capitalize text-slate-500">
              {category} Partner
            </p>
          </div>
          <div className="flex items-center gap-3">
           
            <button
              className="relative grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white"
              aria-label={
                notificationCount > 0
                  ? `Notifications, ${notificationCount} unread`
                  : "Notifications"
              }
            >
              <Bell size={18} />

              {notificationCount > 0 && (
                <span className="absolute -right-1 -top-1 grid h-5 min-w-5 animate-bounce place-items-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                  {notificationCount}
                </span>
              )}
            </button>
            <button
              className="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-emerald-900"
              aria-label="Partner profile"
            >
              <UserRound size={18} />
            </button>
            <button
              onClick={signOut}
              className="grid h-10 w-10 place-items-center rounded-full border border-slate-200 text-slate-600"
              aria-label="Log out"
            >
              <LogOut size={18} />
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-7xl space-y-6 px-4 py-7 sm:px-6 sm:py-10">
        <section>
          <p className="text-sm font-bold text-emerald-800">
            {category} Partner
          </p>
          <h1 className="mt-1 text-3xl font-black tracking-tight sm:text-4xl">
            {greeting}, {businessName}
          </h1>
          <p className="mt-2 text-slate-600">
            Here&apos;s what&apos;s happening with your Essivery business.
          </p>
        </section>

        {referralMessage && (
          <section className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p className="font-black">Referral not linked</p>
            <p className="mt-1 text-sm text-slate-700">
              {referralMessage} You can continue using your Partner dashboard.
            </p>
          </section>
        )}

        <section
          className={`rounded-2xl border p-4 ${statusTone === "rose" ? "border-rose-200 bg-rose-50" : statusTone === "emerald" ? "border-emerald-200 bg-emerald-50" : statusTone === "blue" ? "border-blue-200 bg-blue-50" : "border-amber-200 bg-amber-50"}`}
        >
          <p className="font-black">{statusTitle}</p>
          <p className="mt-1 text-sm text-slate-700">{statusMessage}</p>
        </section>

        {setup?.status === "submitted" ? (
          <StoreStatusToggle
            isOnline={storeOnline}
            onStatusChange={(nextStatus) =>
              setStoreOnline(
                typeof nextStatus?.isOnline === "boolean"
                  ? nextStatus.isOnline
                  : typeof nextStatus?.online === "boolean"
                    ? nextStatus.online
                    : storeOnline,
              )
            }
          />
        ) : (
          <section className="overflow-hidden rounded-[2rem] bg-emerald-950 p-6 text-white shadow-xl sm:p-8">
            <div className="grid gap-7 lg:grid-cols-[1.3fr_.7fr]">
              <div>
                <p className="text-xs font-black uppercase tracking-[.2em] text-emerald-300">
                  Business Setup
                </p>
                <div className="mt-2 flex items-end justify-between gap-3">
                  <h2 className="text-2xl font-black">
                    {["submitted", "resubmitted", "underReview"].includes(
                      setup?.status,
                    )
                      ? "Application under review"
                      : setup?.canSubmit
                        ? setupCompleteTitle
                        : "Complete your business setup"}
                  </h2>
                  <span className="text-sm font-black">
                    {setup
                      ? `${setup.completionPercentage}% complete`
                      : "Loading…"}
                  </span>
                </div>
                <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/15">
                  <div
                    className="h-full rounded-full bg-emerald-400 transition-all"
                    style={{ width: `${setup?.completionPercentage || 0}%` }}
                  />
                </div>
                <p className="mt-4 text-sm leading-6 text-emerald-100">
                  {setupError ||
                    (setup?.canSubmit
                      ? setupCompleteMessage
                      : "Finish your profile to prepare your business for receiving customers on Essivery.")}
                </p>
                <button
                  onClick={() =>
                    navigate(
                      setup?.canSubmit ||
                        [
                          "submitted",
                          "resubmitted",
                          "underReview",
                          "correctionRequired",
                        ].includes(setup?.status)
                        ? "/setup/review"
                        : "/setup",
                    )
                  }
                  className="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-emerald-950"
                >
                  {setup?.status === "correctionRequired"
                    ? "Review corrections"
                    : setup?.canSubmit
                      ? "Review & Submit"
                      : ["submitted", "resubmitted", "underReview"].includes(
                            setup?.status,
                          )
                        ? "View Submission"
                        : "Continue Setup"}{" "}
                  <ChevronRight size={17} />
                </button>
              </div>
              <div className="grid grid-cols-2 gap-2 rounded-2xl bg-white/8 p-4">
                {setup?.steps?.slice(0, 6).map((step) => (
                  <div
                    key={step.code}
                    className="flex items-center gap-2 text-xs"
                  >
                    <span
                      className={`grid h-5 w-5 shrink-0 place-items-center rounded-full ${step.status === "complete" ? "bg-emerald-400 text-emerald-950" : "border border-white/30"}`}
                    >
                      {step.status === "complete" && <Check size={13} />}
                    </span>
                    {step.title}
                  </div>
                )) || (
                  <p className="col-span-2 text-sm text-emerald-100">
                    Loading setup steps…
                  </p>
                )}
              </div>
            </div>
          </section>
        )}

        {setup?.status == "submitted" && <PartnerWallet />}

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {[
            ["Today’s Orders", orders.length, FaShoppingBag],
            ["Today’s Sales", "₹0", FaRupeeSign],
            ["Pending Orders", pendingOrders.length, FaClock],
            // ["Wallet Balance", "—", FaWallet],
            ["Active Products / Services", "0", FaBoxOpen],
            // ["Out of Stock", "0", FaExclamationTriangle],
            ["Low Stock", "0", FaExclamationTriangle],
            ["Completed Orders", "0", FaCheckCircle],
          ].map(([label, value, Icon]) => (
            <article
              key={label}
              className="relative rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
              <div
                className={`grid h-10 w-10 place-items-center rounded-xl ${["Out of Stock", "Low Stock"]?.includes(label) ? "bg-red-100 text-red-700" : "bg-emerald-50 text-emerald-800"}`}
              >
                <Icon size={20} />
              </div>
              <p className="mt-5 text-3xl font-black">{value}</p>
              <p className="mt-1 text-sm text-slate-500">{label}</p>
              {[
                "Today’s Orders",
                "Today’s Sales",
                "Pending Orders",
                "Active Products / Services",
                "Out of Stock",
                "Low Stock",
              ].includes(label) && (
                <button
                  type="button"
                  aria-label={`View ${label.toLowerCase()}`}
                  onClick={() => {
                    if (label === "Active Products / Services") {
                      navigate("/orders");
                      return;
                    }
                    setOrderDialog({
                      filter: label === "Pending Orders" ? "pending" : "today",
                      title: label,
                    });
                  }}
                  className="absolute bottom-4 right-4 grid h-8 w-8 cursor-pointer place-items-center rounded-full border border-slate-200 text-slate-500 transition hover:border-emerald-700 hover:bg-emerald-50 hover:text-emerald-800"
                >
                  <FaArrowRight size={13} />
                </button>
              )}
            </article>
          ))}
        </section>

        <NewOrdersSection
          orders={orders}
          loading={ordersLoading}
          error={ordersError}
          filter={orderFilter}
          onFilterChange={setOrderFilter}
          onRetry={() => setOrdersReload((value) => value + 1)}
          onView={(order) => navigate(`/orders/${order.id || order.publicId}`)}
          onViewAll={() => navigate("/orders")}
        />

        <InventoryAlerts />

        <section>
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-xl font-black">Quick actions</h2>
              <p className="text-sm text-slate-500">
                More Partner tools will become available as setup progresses.
              </p>
            </div>
            <span className="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-600">
              Coming soon
            </span>
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {actions.map(([label, Icon]) => (
              <button
                disabled
                key={label}
                className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-left opacity-75"
              >
                <span className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100">
                  <Icon size={19} />
                </span>
                <span className="font-black">{label}</span>
              </button>
            ))}
          </div>
        </section>

        <section className="rounded-[2rem] border border-slate-200 bg-white p-6 sm:p-8">
          <div className="max-w-2xl">
            <p className="text-xs font-black uppercase tracking-[.2em] text-emerald-700">
              Grow with Essivery
            </p>
            <h2 className="mt-2 text-2xl font-black">
              You&apos;re almost ready to start receiving customers.
            </h2>
            <p className="mt-2 text-slate-600">
              Complete your setup at your own pace. Your Partner dashboard will
              help you reach nearby customers, manage orders digitally, maintain
              your catalogue or services, track earnings, and grow your
              business.
            </p>
          </div>
        </section>
      </main>

      {orderDialog && (
        <OrderSummaryDialog
          filter={orderDialog.filter}
          title={orderDialog.title}
          orders={orderDialog.filter === "pending" ? pendingOrders : orders}
          onClose={() => setOrderDialog(null)}
          onViewMore={() => navigate("/orders")}
          onViewOrder={(order) =>
            navigate(`/orders/${order.id || order.publicId}`)
          }
        />
      )}

      {welcomeOpen && (
        <Modal onClose={closeWelcome}>
          <div className="text-center">
            <div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl">
              🎉
            </div>
            <h2 className="mt-5 text-2xl font-black">
              Welcome to Essivery Partners!
            </h2>
            <p className="mt-2 text-lg font-bold text-emerald-800">
              Welcome, {businessName}!
            </p>
            <p className="mt-3 text-sm leading-6 text-slate-600">
              Your Essivery Partner account is ready. Explore your dashboard and
              complete your business setup whenever you&apos;re ready.
            </p>
            {referralLinked && (
              <p className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-black text-emerald-800">
                Referral linked successfully
              </p>
            )}
            <div className="mt-6 grid gap-3 sm:grid-cols-2">
              <button
                onClick={closeWelcome}
                className="rounded-xl bg-emerald-900 px-5 py-3 font-black text-white"
              >
                Go to Dashboard
              </button>
              <button
                onClick={() => {
                  closeWelcome();
                  navigate("/setup");
                }}
                className="rounded-xl border border-emerald-900 px-5 py-3 font-black text-emerald-900"
              >
                Complete Setup
              </button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}

function DashboardSkeleton() {
  return (
    <div className="min-h-screen bg-[#f4f7f4] text-slate-950">
      <header className="border-b border-slate-200/80 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
          <div className="flex items-center gap-3">
            <Skeleton circle width={44} height={44} />
            <div>
              <Skeleton width={90} height={18} />
              <Skeleton width={70} height={10} />
            </div>
          </div>
          <div className="hidden sm:block">
            <Skeleton width={120} height={14} />
            <Skeleton width={90} height={10} />
          </div>
          <div className="flex gap-2">
            <Skeleton circle width={40} height={40} />
            <Skeleton circle width={40} height={40} />
            <Skeleton circle width={40} height={40} />
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-7xl space-y-6 px-4 py-7 sm:px-6 sm:py-10">
        <section>
          <Skeleton width={130} height={14} />
          <Skeleton className="mt-2" width="min(460px, 90%)" height={42} />
          <Skeleton className="mt-2" width={310} height={16} />
        </section>
        <section className="rounded-[2rem] bg-white p-6 shadow-sm sm:p-8">
          <Skeleton width={100} height={14} />
          <div className="mt-4 grid gap-4 lg:grid-cols-[1.3fr_.7fr]">
            <div>
              <Skeleton height={30} />
              <Skeleton className="mt-4" height={10} />
              <Skeleton className="mt-4" count={2} />
              <Skeleton className="mt-5" width={150} height={42} />
            </div>
            <Skeleton className="h-32" />
          </div>
        </section>
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {Array.from({ length: 8 }, (_, index) => (
            <article
              key={index}
              className="rounded-2xl border border-slate-200 bg-white p-5"
            >
              <Skeleton width={40} height={40} />
              <Skeleton className="mt-5" width={80} height={32} />
              <Skeleton className="mt-2" width={130} height={14} />
            </article>
          ))}
        </section>
        <section className="rounded-[2rem] border border-slate-200 bg-white p-6 sm:p-8">
          <div className="flex justify-between">
            <Skeleton width={180} height={28} />
            <Skeleton width={220} height={38} />
          </div>
          <Skeleton className="mt-6" height={42} count={5} />
        </section>
      </main>
    </div>
  );
}

function Modal({ children, onClose }) {
  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4"
      role="dialog"
      aria-modal="true"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="relative w-full max-w-lg rounded-[2rem] bg-white p-6 shadow-2xl sm:p-8">
        {children}
      </div>
    </div>
  );
}

function OrderSummaryDialog({
  filter,
  title,
  orders,
  onClose,
  onViewMore,
  onViewOrder,
}) {
  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="order-summary-title"
    >
      <div className="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-slate-200 p-5 sm:p-6">
          <div>
            <p className="text-xs font-black uppercase tracking-[.2em] text-emerald-700">
              Order Summary
            </p>
            <h2 id="order-summary-title" className="mt-1 text-2xl font-black">
              {title}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="cursor-pointer rounded-lg px-3 py-2 text-sm font-black text-slate-500 hover:bg-slate-100"
          >
            Close
          </button>
        </div>
        <div className="max-h-[calc(90vh-150px)] overflow-auto p-5 sm:p-6">
          {orders.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-slate-300 p-8 text-center">
              <p className="font-black">
                No {filter === "pending" ? "pending" : "today’s"} orders
              </p>
              <p className="mt-1 text-sm text-slate-500">
                Orders will appear here when they are available.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[680px] text-left text-sm">
                <thead className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                  <tr>
                    {[
                      "Order ID",
                      "Items",
                      "Amount",
                      "Payment",
                      "Time",
                      "Status",
                      "",
                    ].map((heading) => (
                      <th key={heading} className="px-3 py-3 font-black">
                        {heading}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {orders.slice(0, 10).map((order) => (
                    <tr
                      key={order.id || order.publicId}
                      className="border-b border-slate-100 last:border-0"
                    >
                      <td className="px-3 py-4 font-black">
                        #{order.id || order.publicId || "—"}
                      </td>
                      <td className="px-3 py-4">
                        {order.itemsCount ?? order.items ?? 0} Items
                      </td>
                      <td className="px-3 py-4 font-black">
                        {formatOrderCurrency(order.amount)}
                      </td>
                      <td className="px-3 py-4">
                        {order.paymentType || order.payment || "—"}
                      </td>
                      <td className="whitespace-nowrap px-3 py-4 text-slate-500">
                        {formatOrderTime(
                          order.createdAt ||
                            order.created_at ||
                            order.orderTime,
                        )}
                      </td>
                      <td className="px-3 py-4">
                        <StatusBadge status={order.status} />
                      </td>
                      <td className="px-3 py-4">
                        <button
                          type="button"
                          onClick={() => onViewOrder(order)}
                          className="cursor-pointer rounded-md bg-emerald-900 px-2 py-1 text-[11px] font-black text-white"
                        >
                          View
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <div className="mt-6 flex justify-end">
            <button
              type="button"
              onClick={onViewMore}
              className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-emerald-900 px-3 py-2 text-xs font-black text-white"
            >
              View More <FaArrowRight size={11} />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function formatOrderCurrency(value) {
  return `₹${Number(value || 0).toLocaleString("en-IN")}`;
}

function formatOrderTime(value) {
  if (!value) return "Recently";
  const minutes = Math.max(
    1,
    Math.round((Date.now() - new Date(value).getTime()) / 60000),
  );
  return minutes < 60
    ? `${minutes} min${minutes === 1 ? "" : "s"} ago`
    : new Date(value).toLocaleDateString("en-IN", {
        day: "numeric",
        month: "short",
      });
}
