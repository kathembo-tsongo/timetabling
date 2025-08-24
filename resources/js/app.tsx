import "./bootstrap";
import "../css/app.css";

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Toaster } from "react-hot-toast"; // Import Toaster
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const App = ({ Component, pageProps }: AppProps) => {
  const Layout = Component.layout || ((page) => <AuthenticatedLayout>{page}</AuthenticatedLayout>);

  return <Layout>
    <Component {...pageProps} />
  </Layout>;
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <Toaster position="top-right" reverseOrder={false} /> {/* Add Toaster */}
                <App {...props} />
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

export default App;
