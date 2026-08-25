/**
 * Tema claro/oscuro.
 *
 * La clase `dark` de <html> la pone un script en línea del <head> antes de
 * pintar, para que no haya parpadeo. Aquí solo vive lo que ocurre después: el
 * selector del usuario y el seguimiento del tema del sistema operativo.
 */
const consultaOscuro = window.matchMedia('(prefers-color-scheme: dark)');

function aplicarTema(tema) {
    const raiz = document.documentElement;
    const oscuro = tema === 'dark' || (tema === 'system' && consultaOscuro.matches);

    raiz.dataset.theme = tema;
    raiz.classList.toggle('dark', oscuro);
    raiz.style.colorScheme = oscuro ? 'dark' : 'light';

    // El servidor no puede saber qué tema tiene el sistema operativo. Se lo
    // dejamos aquí para que pinte el <html> ya correcto en la siguiente carga.
    document.cookie = `frego_theme_resolved=${oscuro ? 'dark' : 'light'};path=/;max-age=31536000;samesite=lax`;
}

/*
 * Al navegar con `wire:navigate`, Livewire copia los atributos del <html> que
 * venga en la respuesta y borra los que falten. El tema hay que volver a
 * aplicarlo: `data-theme` sí llega del servidor, la clase resuelta no siempre.
 */
document.addEventListener('livewire:navigated', () => {
    aplicarTema(document.documentElement.dataset.theme || 'system');
});

// Si el usuario eligió "Sistema", seguimos los cambios del sistema en vivo.
consultaOscuro.addEventListener('change', () => {
    if (document.documentElement.dataset.theme === 'system') {
        aplicarTema('system');
    }
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('themeSwitcher', () => ({
        tema: document.documentElement.dataset.theme || 'system',

        opciones: [
            { valor: 'light', etiqueta: 'Claro' },
            { valor: 'dark', etiqueta: 'Oscuro' },
            { valor: 'system', etiqueta: 'Sistema' },
        ],

        seleccionar(tema) {
            if (tema === this.tema) {
                return;
            }

            this.tema = tema;

            // La transición se activa solo durante el cambio para que el resto
            // de la interfaz no herede animaciones de color.
            document.documentElement.classList.add('theme-transition');
            aplicarTema(tema);
            window.setTimeout(() => document.documentElement.classList.remove('theme-transition'), 250);

            this.guardar(tema);
        },

        guardar(tema) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch(window.rutaPreferenciaTema, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                },
                body: JSON.stringify({ theme: tema }),
            }).catch(() => {
                // Si falla la red, el tema sigue aplicado en esta pestaña; se
                // volverá a intentar la próxima vez que el usuario lo cambie.
            });
        },
    }));
});

/**
 * Idioma de la interfaz.
 *
 * A diferencia del tema, cambiar de idioma exige volver a pedir la página: los
 * textos los pinta el servidor. Se guarda la preferencia y se recarga.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('localeSwitcher', () => ({
        idioma: document.documentElement.lang || 'es',

        opciones: [
            { valor: 'es', etiqueta: 'Español', corto: 'ES' },
            { valor: 'en', etiqueta: 'English', corto: 'EN' },
        ],

        seleccionar(idioma) {
            if (idioma === this.idioma) {
                return;
            }

            this.idioma = idioma;

            const token = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch(window.rutaPreferenciaIdioma, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                },
                body: JSON.stringify({ locale: idioma }),
            })
                .then(() => window.location.reload())
                .catch(() => window.location.reload());
        },
    }));
});
