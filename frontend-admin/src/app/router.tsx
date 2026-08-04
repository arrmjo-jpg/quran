import React, { Suspense, lazy } from 'react';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import ProtectedRoute from '@/router/ProtectedRoute';
import AppLayout from '@/layouts/AppLayout';
import Spinner from '@/ui/Spinner';

// Lazy-loaded pages
const LoginPage        = lazy(() => import('@/features/auth/pages/LoginPage'));
const DashboardPage    = lazy(() => import('@/features/dashboard/pages/DashboardPage'));
const SeasonsPage      = lazy(() => import('@/features/seasons/pages/SeasonsPage'));
const CountriesPage    = lazy(() => import('@/features/countries/pages/CountriesPage'));
const ContestantsPage  = lazy(() => import('@/features/contestants/pages/ContestantsPage'));
const JudgesPage       = lazy(() => import('@/features/judges/pages/JudgesPage'));
const ApplicationsPage = lazy(() => import('@/features/applications/pages/ApplicationsPage'));
const EvaluationsPage  = lazy(() => import('@/features/evaluations/pages/EvaluationsPage'));
const MediaPage        = lazy(() => import('@/features/media/pages/MediaPage'));
const VideosPage       = lazy(() => import('@/features/videos/pages/VideosPage'));
const StreamingPage    = lazy(() => import('@/features/streaming/pages/StreamingPage'));
const SponsorsPage     = lazy(() => import('@/features/sponsors/pages/SponsorsPage'));
const ContentPage      = lazy(() => import('@/features/content/pages/ContentPage'));
const NotificationsPage = lazy(() => import('@/features/notifications/pages/NotificationsPage'));
const ReportsPage      = lazy(() => import('@/features/reports/pages/ReportsPage'));
const SearchPage       = lazy(() => import('@/features/search/pages/SearchPage'));
const AuditExplorerPage= lazy(() => import('@/features/dashboard/pages/AuditExplorerPage'));
const AboutDiagnosticsPage = lazy(() => import('@/features/dashboard/pages/AboutDiagnosticsPage'));
const ActiveSessionsPage = lazy(() => import('@/features/auth/pages/ActiveSessionsPage'));

const Loading = () => (
  <div className="flex h-screen items-center justify-center">
    <Spinner size="lg" />
  </div>
);

const router = createBrowserRouter([
  // Public routes
  { path: '/login', element: <Suspense fallback={<Loading />}><LoginPage /></Suspense> },

  // Protected routes
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true,                  element: <Suspense fallback={<Loading />}><DashboardPage /></Suspense> },
          { path: 'seasons',              element: <Suspense fallback={<Loading />}><SeasonsPage /></Suspense> },
          { path: 'countries',            element: <Suspense fallback={<Loading />}><CountriesPage /></Suspense> },
          { path: 'contestants',          element: <Suspense fallback={<Loading />}><ContestantsPage /></Suspense> },
          { path: 'judges',               element: <Suspense fallback={<Loading />}><JudgesPage /></Suspense> },
          { path: 'applications',         element: <Suspense fallback={<Loading />}><ApplicationsPage /></Suspense> },
          { path: 'evaluations',          element: <Suspense fallback={<Loading />}><EvaluationsPage /></Suspense> },
          { path: 'media',                element: <Suspense fallback={<Loading />}><MediaPage /></Suspense> },
          { path: 'videos',               element: <Suspense fallback={<Loading />}><VideosPage /></Suspense> },
          { path: 'streaming',            element: <Suspense fallback={<Loading />}><StreamingPage /></Suspense> },
          { path: 'sponsors',             element: <Suspense fallback={<Loading />}><SponsorsPage /></Suspense> },
          { path: 'content',              element: <Suspense fallback={<Loading />}><ContentPage /></Suspense> },
          { path: 'notifications',        element: <Suspense fallback={<Loading />}><NotificationsPage /></Suspense> },
          { path: 'reports',              element: <Suspense fallback={<Loading />}><ReportsPage /></Suspense> },
          { path: 'search',               element: <Suspense fallback={<Loading />}><SearchPage /></Suspense> },
          { path: 'audit-logs',           element: <Suspense fallback={<Loading />}><AuditExplorerPage /></Suspense> },
          { path: 'about',                element: <Suspense fallback={<Loading />}><AboutDiagnosticsPage /></Suspense> },
          { path: 'sessions',             element: <Suspense fallback={<Loading />}><ActiveSessionsPage /></Suspense> },
        ],
      },
    ],
  },
]);

export default function AppRouter(): React.JSX.Element {
  return <RouterProvider router={router} />;
}
