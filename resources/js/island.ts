import { createApp, defineAsyncComponent, type App, type DefineComponent } from "vue";

export type HydrationStrategy = "load" | "idle" | "visible" | "media";

/**
 * Registro de componentes Vue asíncronos mediante import.meta.glob.
 * Vite generará chunks independientes descargados bajo demanda.
 */
const componentModules = import.meta.glob<{ default: DefineComponent }>("./components/**/*.vue");

/**
 * Mapa normalizado para resolver rutas flexibles:
 * Permite 'hero/slogan', 'hero/slogan.vue', './components/hero/slogan.vue', o mayúsculas.
 */
const componentRegistry = new Map<string, () => Promise<{ default: DefineComponent }>>();

for (const [path, importFn] of Object.entries(componentModules)) {
    const cleaned = path.replace(/^\.\/components\//, "").replace(/\.vue$/, "");
    componentRegistry.set(cleaned, importFn);
    componentRegistry.set(cleaned.toLowerCase(), importFn);
}

/**
 * Registro de instancias montadas para control de ciclo de vida y prevención de fugas de memoria.
 */
const mountedInstances = new WeakMap<HTMLElement, App>();

/**
 * Resuelve y extrae las props de la isla:
 * 1. Prioriza un tag <script type="application/json" class="vue-island-props"> interno (evita problemas de escaping HTML).
 * 2. Alternativa: lee el atributo data-props en formato JSON.
 */
function extractProps(el: HTMLElement): Record<string, unknown> {
    const scriptProps = el.querySelector<HTMLScriptElement>('script[type="application/json"].vue-island-props');
    if (scriptProps?.textContent) {
        try {
            return JSON.parse(scriptProps.textContent);
        } catch (error) {
            console.error("[Vue Island] Error al parsear props desde el script interno:", error);
            return {};
        }
    }

    if (el.dataset.props) {
        try {
            return JSON.parse(el.dataset.props);
        } catch (error) {
            console.error("[Vue Island] Error al parsear el atributo data-props:", error);
            return {};
        }
    }

    return {};
}

/**
 * Monta una isla individual aplicando la instancia de Vue.
 */
export async function mountIsland(el: HTMLElement): Promise<App | null> {
    if (el.dataset.vueMounted === "true") {
        return mountedInstances.get(el) ?? null;
    }

    const componentName = el.dataset.vue?.trim();
    if (!componentName) {
        return null;
    }

    // Normalizar búsqueda en el registro
    const normalizedKey = componentName.replace(/^\.\/components\//, "").replace(/\.vue$/, "");
    const importFn = componentRegistry.get(normalizedKey) || componentRegistry.get(normalizedKey.toLowerCase());

    if (!importFn) {
        console.error(
            `[Vue Island] Componente '${componentName}' no encontrado en ./components/**/*.vue.`,
            `Rutas disponibles:`,
            Array.from(componentRegistry.keys())
        );
        return null;
    }

    // Marcar como procesado para prevenir carreras y doble montaje
    el.dataset.vueMounted = "true";

    const props = extractProps(el);
    const AsyncComponent = defineAsyncComponent({
        loader: () => importFn().then((mod) => mod.default),
        onError: (error) => {
            console.error(`[Vue Island] Fallo al cargar el chunk del componente '${componentName}':`, error);
        },
    });

    const app = createApp(AsyncComponent, props);

    // Si hay un contenedor dedicado o fallback/slot, limpiamos antes de montar
    const fallback = el.querySelector<HTMLElement>(".vue-island-fallback");
    if (fallback) {
        fallback.remove();
    }

    app.mount(el);
    mountedInstances.set(el, app);

    if (import.meta.env.DEV) {
        console.info(`[Vue Island] Montado: '${componentName}'`, {
            props,
            target: `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}`,
        });
    }

    return app;
}

/**
 * Desmonta una isla y libera los recursos de Vue.
 */
export function unmountIsland(el: HTMLElement): void {
    const app = mountedInstances.get(el);
    if (app) {
        app.unmount();
        mountedInstances.delete(el);
        delete el.dataset.vueMounted;
    }
}

/**
 * Aplica la estrategia de hidratación correspondiente según el atributo data-client.
 */
function hydrateWithStrategy(el: HTMLElement): void {
    const strategy = (el.dataset.client ?? "load") as HydrationStrategy;

    switch (strategy) {
        case "idle": {
            if ("requestIdleCallback" in window) {
                (window as Window & { requestIdleCallback: (cb: () => void) => void }).requestIdleCallback(() => {
                    mountIsland(el);
                });
            } else {
                setTimeout(() => mountIsland(el), 200);
            }
            break;
        }

        case "visible": {
            if ("IntersectionObserver" in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            observer.disconnect();
                            mountIsland(el);
                        }
                    });
                }, { rootMargin: "150px" });
                observer.observe(el);
            } else {
                mountIsland(el);
            }
            break;
        }

        case "media": {
            const mediaQuery = el.dataset.clientMedia;
            if (mediaQuery && window.matchMedia) {
                const mqList = window.matchMedia(mediaQuery);
                const checkAndMount = (e: MediaQueryList | MediaQueryListEvent) => {
                    if (e.matches) {
                        mountIsland(el);
                    }
                };
                if (mqList.matches) {
                    mountIsland(el);
                } else {
                    mqList.addEventListener("change", checkAndMount, { once: true });
                }
            } else {
                mountIsland(el);
            }
            break;
        }

        case "load":
        default: {
            mountIsland(el);
            break;
        }
    }
}

/**
 * Busca y monta todas las islas no montadas en el ámbito especificado.
 * Usa el selector CSS de atributo correcto: [data-vue]
 */
export function mountIslands(root: ParentNode = document): void {
    const elements = root.querySelectorAll<HTMLElement>("[data-vue]:not([data-vue-mounted='true'])");
    elements.forEach((el) => {
        hydrateWithStrategy(el);
    });
}

// Inicialización automática respetando el ciclo de vida del DOM
if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => mountIslands());
    } else {
        mountIslands();
    }

    // Exponer hook para navegaciones dinámicas (Livewire, Turbo, AJAX)
    window.addEventListener("vue-islands:mount", () => mountIslands());
    document.addEventListener("livewire:navigated", () => mountIslands());
}
