// src/index.js
import { createRoot } from '@wordpress/element';
import App from './App';
import { ToastProvider } from './ToastContext';
import '../assets/css/style.scss';

const root = document.getElementById('wbm-root');

if (root) {
  createRoot(root).render(
    <ToastProvider>
      <App />
    </ToastProvider>
  );
}
