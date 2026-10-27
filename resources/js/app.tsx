import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster, toast } from 'sonner';

const appName = import.meta.env.VITE_APP_NAME || 'Pelaporan Belanja Modal KCD X';

// Tampilkan flash sekali per kunjungan lewat event Inertia — bukan useEffect
// di layout, yang bisa re-fire saat komponen di-mount ulang.
router.on('success', (event) => {
    const flash = (event.detail.page.props as any)?.flash;
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.warning) toast.warning(flash.warning);
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob<any>('./Pages/**/*.tsx')
        ) as any,
    setup({ el, App, props }) {
        if (el) {
            createRoot(el).render(
                <>
                    <App {...props} />
                    <Toaster richColors position="top-right" closeButton />
                </>
            );
        }
    },
    progress: {
        color: '#2563eb',
    },
});
