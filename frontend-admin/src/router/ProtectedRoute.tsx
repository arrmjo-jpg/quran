import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/core/auth/AuthContext';

export default function ProtectedRoute(): React.JSX.Element {
  const { isAuthed } = useAuth();
  return isAuthed ? <Outlet /> : <Navigate to="/login" replace />;
}
