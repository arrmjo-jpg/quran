import React, { Suspense, lazy } from 'react';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import ProtectedRoute from '@/router/ProtectedRoute';
import RequirePermission from '@/router/RequirePermission';
import AppLayout from '@/layouts/AppLayout';
import Spinner from '@/ui/Spinner';

// Lazy-loaded pages
const LoginPage        = lazy(() => import('@/features/auth/pages/LoginPage'));
const DashboardPage    = lazy(() => import('@/features/dashboard/pages/DashboardPage'));
const SeasonsPage      = lazy(() => import('@/features/seasons/pages/SeasonsPage'));
const CountriesPage    = lazy(() => import('@/features/countries/pages/CountriesPage'));
const CentersPage      = lazy(() => import('@/features/centers/pages/CentersPage'));
const CirclesPage      = lazy(() => import('@/features/circles/pages/CirclesPage'));
const MembershipsPage  = lazy(() => import('@/features/memberships/pages/MembershipsPage'));
const RolesPage        = lazy(() => import('@/features/roles/pages/RolesPage'));
const UsersPage        = lazy(() => import('@/features/users/pages/UsersPage'));
const ContestantsPage  = lazy(() => import('@/features/contestants/pages/ContestantsPage'));
const ContestantIdentityPage = lazy(() => import('@/features/contestants/pages/ContestantIdentityPage'));
const UserDetailPage   = lazy(() => import('@/features/users/pages/UserDetailPage'));
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
const ActivityLogPage  = lazy(() => import('@/features/activity/pages/ActivityLogPage'));
const AboutDiagnosticsPage = lazy(() => import('@/features/dashboard/pages/AboutDiagnosticsPage'));
const ActiveSessionsPage = lazy(() => import('@/features/auth/pages/ActiveSessionsPage'));
const ProfilePage      = lazy(() => import('@/features/profile/pages/ProfilePage'));

const Loading = () => (
  <div className="flex h-screen items-center justify-center">
    <Spinner size="lg" />
  </div>
);

/**
 * A protected screen.
 *
 * The permission is looked up from ROUTE_PERMISSIONS by path rather than
 * written here, so this file never names one and cannot drift from the
 * sidebar. A path that map does not list stays open, which is the behaviour
 * every route had before.
 */
function page(path: string, element: React.ReactNode) {
  return {
    path,
    element: (
      <RequirePermission path={`/${path}`}>
        <Suspense fallback={<Loading />}>{element}</Suspense>
      </RequirePermission>
    ),
  };
}

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
          page('seasons', <SeasonsPage />),
          page('countries', <CountriesPage />),
          page('centers', <CentersPage />),
          page('circles', <CirclesPage />),
          page('memberships', <MembershipsPage />),
          page('roles', <RolesPage />),
          page('users', <UsersPage />),
          page('users/:id', <UserDetailPage />),
          page('contestants', <ContestantsPage />),
          page('contestants/:id', <ContestantIdentityPage />),
          page('judges', <JudgesPage />),
          page('applications', <ApplicationsPage />),
          page('evaluations', <EvaluationsPage />),
          page('media', <MediaPage />),
          page('videos', <VideosPage />),
          page('streaming', <StreamingPage />),
          page('sponsors', <SponsorsPage />),
          page('content', <ContentPage />),
          page('notifications', <NotificationsPage />),
          page('reports', <ReportsPage />),
          page('search', <SearchPage />),
          page('audit-logs', <ActivityLogPage />),
          page('about', <AboutDiagnosticsPage />),
          page('sessions', <ActiveSessionsPage />),
          page('profile', <ProfilePage />),
        ],
      },
    ],
  },
]);

export default function AppRouter(): React.JSX.Element {
  return <RouterProvider router={router} />;
}
