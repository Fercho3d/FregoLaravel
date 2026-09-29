import flatpickr from 'flatpickr';

/**
 * Selector de rango de fechas (el campo «Fechas» de los listados).
 *
 * Abre un calendario al hacer clic y deja elegir inicio y fin. Escribe el valor
 * en la propiedad de Livewire con el MISMO formato que ya espera el servidor
 * ("dd/mm/aaaa - dd/mm/aaaa"), pero de forma diferida: no dispara la consulta:
 * eso lo hace el botón «Filtrar», para que capturar no se sienta pesado.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('dateRangePicker', (valorInicial) => ({
        fp: null,

        init() {
            this.fp = flatpickr(this.$refs.input, {
                mode: 'range',
                dateFormat: 'd/m/Y',
                locale: { rangeSeparator: ' - ', firstDayOfWeek: 1 },
                defaultDate: this.parse(valorInicial),
                onChange: (fechas, texto) => {
                    // Un rango solo se aplica cuando están las dos fechas; al
                    // limpiar el calendario se vacía el filtro.
                    if (fechas.length === 2) {
                        this.$wire.set('dates', texto, false);
                    } else if (fechas.length === 0) {
                        this.$wire.set('dates', '', false);
                    }
                },
            });

            // Si el servidor limpia el filtro («Limpiar filtros»), vaciar también
            // el calendario, que vive fuera del alcance de Livewire (wire:ignore).
            this.$watch('$wire.dates', (valor) => {
                if (!valor && this.fp.selectedDates.length) {
                    this.fp.clear();
                }
            });
        },

        parse(valor) {
            return valor && valor.includes(' - ') ? valor.split(' - ') : null;
        },

        destroy() {
            this.fp?.destroy();
        },
    }));
});

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
    document.cookie = `app_theme_resolved=${oscuro ? 'dark' : 'light'};path=/;max-age=31536000;samesite=lax`;
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

/**
 * Ancho de columnas ajustable a mano.
 *
 * Los anchos NO se escriben en cada `<th>`: Livewire vuelve a pintar la tabla en
 * cada filtro y se perderían. Se escriben como reglas CSS en un `<style>` con
 * `wire:ignore` —que el morph no toca— y se guardan en este navegador, así que
 * cada quien deja el listado como le acomoda sin tocar la base de datos.
 *
 * Arrastrar el borde derecho de la cabecera cambia el ancho; doble clic lo
 * devuelve a como estaba.
 *
 * `fijas` deja las primeras columnas quietas al recorrer la tabla a lo ancho
 * (casilla, booking, fecha y número). Su `left` depende del ancho real de las
 * anteriores, que cambia con el contenido y con el arrastre, así que se mide.
 * Solo cabecera y cuerpo: la primera celda del pie abarca varias columnas y,
 * fija, taparía los totales.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('columnResizer', (llave, fijas = 0) => ({
        anchos: {},
        minimo: 56,

        init() {
            try {
                this.anchos = JSON.parse(localStorage.getItem(llave) || '{}');
            } catch {
                this.anchos = {};
            }

            this.pintar();

            if (fijas) {
                // Filtrar o paginar cambia el ancho de las fijas sin pasar por `pintar`.
                this.observador = new ResizeObserver(() => this.pintar());
                this.$el.querySelectorAll(`thead th:nth-child(-n+${fijas})`).forEach((th) => this.observador.observe(th));
            }
        },

        // Al salir con `wire:navigate` las cabeceras se desprenden y el
        // observador avisaba una última vez sobre un componente ya destruido.
        destroy() {
            this.observador?.disconnect();
        },

        pintar() {
            const id = this.$el.id;

            const reglas = Object.entries(this.anchos).map(
                ([columna, ancho]) =>
                    `#${id} th:nth-child(${columna}),#${id} td:nth-child(${columna})` +
                    `{width:${ancho}px;max-width:${ancho}px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}`,
            );
            this.$refs.reglas.textContent = reglas.join('');

            if (!fijas) {
                return;
            }

            const fija = (n) => `#${id} thead tr>:nth-child(${n}),#${id} tbody tr>:nth-child(${n})`;
            const todas = fija(`-n+${fijas}`);
            let izquierda = 0;

            this.$el.querySelectorAll(`thead th:nth-child(-n+${fijas})`).forEach((th, i) => {
                reglas.push(`${fija(i + 1)}{left:${izquierda}px}`);
                izquierda += th.getBoundingClientRect().width;
            });

            // Fondo opaco para que no se transparente lo que pasa por debajo; el
            // marcado se pinta encima porque en oscuro es translúcido.
            reglas.push(
                `${todas}{position:sticky;z-index:1;background-color:var(--panel)}`,
                `#${id} tbody tr:not(.row-picked):hover>:nth-child(-n+${fijas}){background-color:var(--raised)}`,
                `#${id} tbody tr.row-picked>:nth-child(-n+${fijas}){background-image:linear-gradient(var(--pick-bg),var(--pick-bg))}`,
                `${fija(fijas)}{box-shadow:inset -1px 0 0 var(--line)}`,
                `#${id} tbody tr.row-picked>:first-child{box-shadow:inset 3px 0 0 0 var(--pick-line)}`,
            );
            this.$refs.reglas.textContent = reglas.join('');
        },

        guardar() {
            Object.keys(this.anchos).length
                ? localStorage.setItem(llave, JSON.stringify(this.anchos))
                : localStorage.removeItem(llave);
        },

        arrastrar(evento, columna) {
            evento.preventDefault();

            const celda = evento.target.closest('th');
            const desdeX = evento.clientX;
            const desdeAncho = celda.offsetWidth;

            const mover = (e) => {
                this.anchos[columna] = Math.max(this.minimo, Math.round(desdeAncho + e.clientX - desdeX));
                this.pintar();
            };

            const soltar = () => {
                document.removeEventListener('mousemove', mover);
                document.removeEventListener('mouseup', soltar);
                document.body.classList.remove('select-none');
                this.guardar();
            };

            // Sin esto, arrastrar selecciona el texto de la tabla.
            document.body.classList.add('select-none');
            document.addEventListener('mousemove', mover);
            document.addEventListener('mouseup', soltar);
        },

        restablecer(columna) {
            delete this.anchos[columna];
            this.guardar();
            this.pintar();
        },
    }));
});

/**
 * Entrada de pantalla.
 *
 * `wire:navigate` cambia el contenido sin recargar, y el salto se sentía seco.
 * El `<main>` ya trae la clase puesta por el servidor (así también se anima la
 * primera carga); aquí solo hay que volver a dispararla en cada navegación.
 *
 * No se anima en los cambios normales de Livewire —filtrar, paginar, marcar una
 * casilla—: eso sería un parpadeo constante y molesto.
 */
document.addEventListener('livewire:navigated', () => {
    const pantalla = document.querySelector('[data-pantalla]');

    if (!pantalla) {
        return;
    }

    pantalla.classList.remove('page-enter');
    // Leer una medida obliga al navegador a rehacer el cálculo: sin esto, quitar
    // y poner la clase en el mismo cuadro no reinicia la animación.
    void pantalla.offsetWidth;
    pantalla.classList.add('page-enter');
});

/**
 * Campo de importe: solo deja capturar números y un punto decimal, y va
 * poniendo las comas de miles mientras se escribe («2929.91» → «2,929.91»).
 *
 * El servidor quita las comas antes de validar, así que a Livewire se le manda
 * el texto tal como se ve. Se usa junto con `wire:model` en el mismo `<input>`.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('campoImporte', () => ({
        init() {
            this.formatear();

            // Cuando el servidor cambia el valor (p. ej. el precio del servicio
            // elegido), llega sin comas: se vuelve a formatear al pintarse.
            const propiedad = this.$el.getAttribute('wire:model');
            if (propiedad) {
                this.$wire.$watch(propiedad, () => this.$nextTick(() => this.formatear()));
            }

            this.$el.addEventListener('input', () => this.formatear(true));
        },

        formatear(avisar = false) {
            const campo = this.$el;
            const antes = campo.value;

            // Cuántos caracteres válidos hay antes del cursor, para devolverlo
            // al mismo lugar después de meter o quitar comas.
            const cursor = campo.selectionStart ?? antes.length;
            const validosAntes = antes.slice(0, cursor).replace(/[^\d.]/g, '').length;

            let limpio = antes.replace(/[^\d.]/g, '');
            const punto = limpio.indexOf('.');
            if (punto !== -1) {
                limpio = limpio.slice(0, punto + 1) + limpio.slice(punto + 1).replace(/\./g, '');
            }

            const [entero, decimales] = limpio.split('.');
            const conComas = entero.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const nuevo = decimales === undefined ? conComas : `${conComas}.${decimales}`;

            if (nuevo === antes) {
                return;
            }

            campo.value = nuevo;

            if (document.activeElement === campo) {
                let posicion = 0;
                for (let vistos = 0; posicion < nuevo.length && vistos < validosAntes; posicion++) {
                    if (nuevo[posicion] !== ',') {
                        vistos++;
                    }
                }
                campo.setSelectionRange(posicion, posicion);
            }

            // Livewire ya leyó el valor sin formato: se le vuelve a avisar.
            if (avisar) {
                campo.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    }));
});

/**
 * Reporte de fallas del navegador.
 *
 * Lo que solo ve el navegador no dejaba rastro: un botón que se queda girando
 * porque la respuesta nunca llegó, una acción que falla o un error de
 * JavaScript. Se le avisa al servidor (`ReporteDelNavegadorController`), que lo
 * deja en el log, y una acción sin respuesta además llega por correo.
 *
 * Pocas y sin repetir por página: esto no debe convertirse en ruido.
 */
(() => {
    const SIN_RESPUESTA_MS = 35000; // PHP corta a los 30 s: más que esto ya no llega.
    const LENTA_MS = 10000;
    const enviados = new Set();

    const reportar = (tipo, mensaje, extra = {}) => {
        const llave = `${tipo}|${mensaje}`;

        if (enviados.has(llave) || enviados.size >= 10) {
            return;
        }
        enviados.add(llave);

        fetch('/diagnostico/navegador', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ tipo, mensaje: String(mensaje).slice(0, 1000), pantalla: location.href.slice(0, 500), ...extra }),
        }).catch(() => {});
    };

    // «booking-detail → confirm», igual que `ContextoPeticion` en el servidor.
    const accion = (payload) => {
        try {
            return JSON.parse(payload)
                .components.map((c) => {
                    const metodos = [...(c.calls ?? []).map((x) => x.method), ...Object.keys(c.updates ?? {})];

                    return `${JSON.parse(c.snapshot).memo.name} → ${metodos.join(', ') || 'repintar'}`;
                })
                .join(' | ')
                .slice(0, 500);
        } catch {
            return null;
        }
    };

    window.addEventListener('error', (e) => {
        // Las extensiones del navegador también lanzan errores; no son nuestros.
        if (e.filename && !e.filename.startsWith(location.origin)) {
            return;
        }
        reportar('js', `${e.message} (${e.filename ?? '?'}:${e.lineno ?? '?'})`);
    });

    window.addEventListener('unhandledrejection', (e) => reportar('js', `Promesa rechazada: ${e.reason?.message ?? e.reason}`));

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ payload, respond, fail }) => {
            const inicio = performance.now();
            const que = accion(payload);
            const vigia = setTimeout(
                () => reportar('sin_respuesta', `${que ?? 'acción'} sin respuesta tras ${SIN_RESPUESTA_MS / 1000} s`, { accion: que, ms: SIN_RESPUESTA_MS }),
                SIN_RESPUESTA_MS,
            );

            respond(() => {
                clearTimeout(vigia);
                const ms = Math.round(performance.now() - inicio);

                if (ms >= LENTA_MS) {
                    reportar('lenta', `${que ?? 'acción'} tardó ${Math.round(ms / 1000)} s`, { accion: que, ms });
                }
            });

            // 419 (sesión caducada) lo resuelve Livewire con su aviso; y un 500 ya
            // lo registró el servidor. Aquí interesa lo que el servidor no ve,
            // incluida la red caída (sin estado).
            fail(({ status }) => {
                clearTimeout(vigia);

                if (status !== 419 && !(status >= 500)) {
                    reportar('fallo', `${que ?? 'acción'} falló (HTTP ${status})`, { accion: que });
                }
            });
        });
    });
})();
