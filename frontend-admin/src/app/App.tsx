import React from 'react';
import { ErrorBoundary } from '@/core/api/ErrorBoundary';
import Providers from './providers';
import AppRouter from './router';

export default function App(): React.JSX.Element {
  return (
    <ErrorBoundary>
      <Providers>
        <AppRouter />
      </Providers>
    </ErrorBoundary>
  );
}
