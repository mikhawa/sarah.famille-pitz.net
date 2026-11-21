import { Controller } from '@hotwired/stimulus';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/dist/photoswipe.css';
import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from './csrf_protection_controller.js';

/*
 * Visionneuse plein écran des photos (PhotoSwipe 5, licence MIT) : glisser d'une photo à l'autre, pincer ou
 * double-toucher pour zoomer, flèches du clavier.
 *
 * Sur l'élément qui porte data-controller="lightbox", chaque lien <a data-pswp-src data-pswp-width data-pswp-height>
 * devient une photo de la visionneuse ; son href (la fiche du média) reste le lien suivi sans JavaScript.
 * Les autres liens (vidéos, musique) ne sont pas concernés.
 *
 * Album paginé : si la valeur data-lightbox-items-value (liste JSON de toutes les photos de l'album : id, src, width,
 * height, href, commentsUrl, alt) est fournie, la visionneuse parcourt cette liste complète, quelle que soit la page.
 * Un clic sur une vignette (data-lightbox-id) l'ouvre à la place de cette photo dans la liste ; la vignette n'est
 * utilisée que pour l'animation de zoom (fondu si la photo n'est pas sur la page). Sans cette valeur, la visionneuse
 * ne connaît que les liens de la page, comme avant.
 *
 * Fil de l'accueil (trop de photos pour les embarquer) : data-lightbox-items-url-value donne la route JSON de la même
 * liste, chargée au premier clic puis gardée en mémoire. Si le chargement échoue (réseau, session expirée) ou si la
 * photo n'est pas dans la liste, le lien vers la fiche de la photo est suivi.
 *
 * Petits mots : le bouton 💬 (avec leur nombre) ouvre ou referme un panneau par-dessus la photo. Le fragment HTML vient
 * de data-comments-url (route media_comments, rendu et échappé par Twig) ; il est chargé à chaque photo affichée,
 * gardé en mémoire le temps de la visionneuse. Le choix ouvert / fermé est retenu dans le navigateur.
 * Le formulaire du panneau est envoyé sans quitter la visionneuse : la réponse (fragment à jour) remplace le panneau.
 */
const PANEL_STORAGE_KEY = 'lightbox-comments-open';

function readPanelPreference() {
    try {
        return window.localStorage.getItem(PANEL_STORAGE_KEY) !== '0';
    } catch {
        return true;
    }
}

function savePanelPreference(open) {
    try {
        window.localStorage.setItem(PANEL_STORAGE_KEY, open ? '1' : '0');
    } catch {
        // Stockage indisponible (navigation privée…) : le choix vaut pour cette visionneuse seulement
    }
}

export default class extends Controller {
    static values = { items: Array, itemsUrl: String };

    connect() {
        const fullList = (this.hasItemsValue && this.itemsValue.length > 0) || (this.hasItemsUrlValue && this.itemsUrlValue !== '');

        this.lightbox = new PhotoSwipeLightbox({
            // Liste complète : les clics sont gérés plus bas (ouverture à l'index global) ; sinon liens de la page
            ...(fullList ? {} : { gallery: this.element, children: 'a[data-pswp-src]' }),
            pswpModule: () => import('photoswipe'),
            bgOpacity: 0.92,
            showHideAnimationType: 'zoom',
            closeTitle: 'Fermer (Échap)',
            zoomTitle: 'Zoomer',
            arrowPrevTitle: 'Photo précédente',
            arrowNextTitle: 'Photo suivante',
            errorMsg: 'Cette photo n\'a pas pu être chargée.',
            indexIndicatorSep: ' sur ',
        });

        // Données propres au lien (fiche, petits mots) rattachées à la diapo, comme celles de la liste complète
        this.lightbox.addFilter('domItemData', (itemData, element, linkEl) => ({
            ...itemData,
            href: linkEl?.getAttribute('href') ?? null,
            commentsUrl: linkEl?.dataset.commentsUrl ?? null,
            current: linkEl?.dataset.lightboxCurrent !== undefined,
        }));

        if (fullList) {
            this.setupFullList();
        }

        this.lightbox.on('uiRegister', () => {
            this.registerComments(this.lightbox.pswp);

            // Bouton « fiche » : ouvre la page du média (infos, téléchargement, écrire un petit mot)
            this.lightbox.pswp.ui.registerElement({
                name: 'media-page',
                order: 9,
                isButton: true,
                tagName: 'a',
                title: 'Voir la fiche de la photo',
                html: '<span class="pswp-media-page" aria-hidden="true">ℹ️</span>',
                onInit: (element, pswp) => {
                    const update = () => {
                        const data = pswp.currSlide?.data;
                        element.href = data?.href ?? '#';
                        element.hidden = !data?.href || data.current === true;
                    };
                    pswp.on('change', update);
                    update();
                },
            });
        });

        this.lightbox.init();
    }

    /**
     * Visionneuse sur toute la liste (fournie dans la page, ou chargée au premier clic depuis itemsUrl) : clic sur une
     * vignette → ouverture à l'index de cette photo dans la liste.
     * Le clic avec Ctrl, Maj, Alt ou le bouton du milieu garde son effet habituel (ouvrir la fiche dans un onglet).
     */
    setupFullList() {
        let items = this.hasItemsValue && this.itemsValue.length > 0 ? this.itemsValue : null;
        let indexById = null;
        let loading = false;
        const tileOf = (id) => this.element.querySelector(`a[data-lightbox-id="${CSS.escape(String(id))}"]`);

        const setItems = (list) => {
            items = list;
            indexById = new Map(list.map((item, index) => [String(item.id), index]));
        };
        if (items) {
            setItems(items);
        }

        // Liste JSON de la route du fil, ou null (échec, session expirée : redirection vers la connexion)
        const fetchItems = async () => {
            try {
                const response = await fetch(this.itemsUrlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok || response.redirected || !(response.headers.get('Content-Type') ?? '').includes('json')) {
                    return null;
                }
                const list = await response.json();

                return Array.isArray(list) ? list : null;
            } catch {
                return null;
            }
        };

        // Repli : la fiche de la photo, comme sans JavaScript
        const followLink = (tile) => {
            window.Turbo ? window.Turbo.visit(tile.href) : window.location.assign(tile.href);
        };

        // Vignette présente sur la page : sert à l'animation de zoom et de retour (sinon : simple fondu)
        this.lightbox.addFilter('itemData', (itemData) => {
            const tile = tileOf(itemData.id);

            return tile ? { ...itemData, element: tile, msrc: tile.querySelector('img')?.currentSrc || undefined } : itemData;
        });

        this.onTileClick = async (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey || window.pswp) {
                return;
            }
            const tile = event.target.closest('a[data-lightbox-id]');
            if (!tile || !this.element.contains(tile)) {
                return;
            }
            event.preventDefault();
            const point = event.clientX || event.clientY ? { x: event.clientX, y: event.clientY } : null;

            if (!items) {
                // Premier clic : liste à charger ; les clics suivants pendant le chargement sont ignorés
                if (loading) {
                    return;
                }
                loading = true;
                const list = await fetchItems();
                loading = false;
                if (!list) {
                    followLink(tile);
                    return;
                }
                setItems(list);
            }

            const index = indexById.get(tile.dataset.lightboxId);
            if (index === undefined) {
                // Photo absente de la liste : le lien vers la fiche est suivi
                followLink(tile);
                return;
            }
            this.lightbox?.loadAndOpen(index, items, point);
        };
        this.element.addEventListener('click', this.onTileClick);
    }

    /**
     * Bouton 💬 dans la barre du haut + panneau des petits mots (et formulaire) en bas de la photo
     */
    registerComments(pswp) {
        const cache = new Map();
        // Photos qui ont reçu un petit mot depuis la visionneuse
        const posted = new Set();
        let open = readPanelPreference();
        let button = null;
        let panel = null;
        let shown = null;

        const currentUrl = () => pswp.currSlide?.data.commentsUrl ?? null;

        const message = (text) => {
            const content = document.createDocumentFragment();
            content.append(Object.assign(document.createElement('p'), { className: 'lightbox-comments-empty', textContent: text }));

            return { content, count: 0 };
        };

        // Fragment rendu par Twig (contenu échappé) : analysé hors du document avant insertion
        const parse = (html) => {
            const template = document.createElement('template');
            template.innerHTML = html;
            const root = template.content.querySelector('.lightbox-comments');

            return root ? { content: template.content, count: Number(root.dataset.count ?? 0) } : null;
        };

        // Réponse HTML de la route media_comments, ou null (session expirée : redirection vers la connexion)
        const readFragment = async (response) => {
            if (response.redirected || !(response.headers.get('Content-Type') ?? '').includes('text/html')) {
                return null;
            }

            return parse(await response.text());
        };

        const render = () => {
            const url = currentUrl();
            const entry = url ? cache.get(url) : null;
            const count = entry?.count ?? 0;

            button.hidden = !url;
            button.setAttribute('aria-expanded', String(open));
            button.title = open ? 'Cacher les petits mots' : 'Voir les petits mots';
            button.setAttribute('aria-label', button.title);
            button.querySelector('.pswp-comments-count').textContent = count > 0 ? String(count) : '';

            panel.hidden = !open || !url;
            // Contenu remplacé seulement s'il a changé : un petit mot en cours d'écriture n'est pas effacé
            const next = entry ?? message('Chargement des petits mots…');
            if (shown?.url !== url || shown?.entry !== entry) {
                panel.replaceChildren(next.content.cloneNode(true));
                shown = { url, entry };
            }
        };

        const load = async (url) => {
            if (cache.has(url)) {
                return;
            }
            let entry = null;
            try {
                const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
                entry = response.ok ? await readFragment(response) : null;
            } catch {
                // Connexion interrompue : message ci-dessous
            }
            cache.set(url, entry ?? message('Les petits mots n\'ont pas pu être chargés.'));
            if (currentUrl() === url) {
                render();
            }
        };

        const showFormError = (form, text) => {
            let error = form.querySelector('[data-lightbox-form-error]');
            if (!error) {
                error = Object.assign(document.createElement('p'), { className: 'lightbox-form-error' });
                error.dataset.lightboxFormError = '';
                error.setAttribute('role', 'alert');
                form.prepend(error);
            }
            error.textContent = text;
        };

        const submit = async (form) => {
            const url = currentUrl();
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            // Jeton CSRF sans état : champ + cookie, et en-tête pour la requête fetch
            generateCsrfToken(form);
            let entry = null;
            let response = null;
            try {
                response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'text/html', ...generateCsrfHeaders(form) },
                    credentials: 'same-origin',
                });
                entry = [200, 422].includes(response.status) ? await readFragment(response) : null;
            } catch {
                // Connexion interrompue : message ci-dessous
            }
            removeCsrfToken(form);

            if (!entry) {
                submitButton.disabled = false;
                showFormError(form, response?.redirected
                    ? 'Ta session a expiré : reconnecte-toi, puis réessaie.'
                    : 'Le petit mot n\'a pas pu être envoyé. Réessaie dans un instant.');
                return;
            }

            // Envoyé (liste à jour) ou refusé (message d'erreur du formulaire) : le panneau est remplacé
            cache.set(url, entry);
            if (response.status === 200) {
                posted.add(url);
            }
            if (currentUrl() === url) {
                render();
                if (response.status === 200) {
                    panel.scrollTop = panel.scrollHeight;
                } else {
                    panel.querySelector('textarea')?.focus();
                }
            }
        };

        pswp.ui.registerElement({
            name: 'comments-toggle',
            order: 8,
            isButton: true,
            html: '<span class="pswp-comments-icon" aria-hidden="true">💬</span><span class="pswp-comments-count"></span>',
            onInit: (element) => {
                button = element;
                element.setAttribute('aria-controls', 'pswp-comments-panel');
            },
            onClick: () => {
                open = !open;
                savePanelPreference(open);
                render();
            },
        });

        pswp.ui.registerElement({
            name: 'comments-panel',
            className: 'pswp__comments-panel',
            // Initialisé après le bouton 💬 (ordre croissant), dont render() a besoin
            order: 30,
            appendTo: 'root',
            tagName: 'section',
            onInit: (element) => {
                panel = element;
                element.id = 'pswp-comments-panel';
                element.setAttribute('aria-label', 'Petits mots');
                // La molette et les gestes servent à faire défiler les messages, pas à zoomer ou changer de photo
                for (const type of ['wheel', 'pointerdown', 'touchstart']) {
                    element.addEventListener(type, (event) => event.stopPropagation(), { passive: true });
                }
                // Pendant l'écriture, les flèches et Échap agissent sur le texte, pas sur la visionneuse
                element.addEventListener('keydown', (event) => {
                    if (event.target.closest('textarea, input, button')) {
                        event.stopPropagation();
                    }
                });
                element.addEventListener('submit', (event) => {
                    const form = event.target.closest('form[data-lightbox-comment-form]');
                    if (form) {
                        event.preventDefault();
                        submit(form);
                    }
                });

                pswp.on('change', update);
                update();
            },
        });

        const update = () => {
            const url = currentUrl();
            render();
            if (url) {
                load(url);
            }
        };

        // Visionneuse ouverte sur la fiche : à la fermeture, la liste des petits mots de la page est rechargée
        pswp.on('destroy', () => {
            const current = this.element.querySelector('[data-lightbox-current]')?.dataset.commentsUrl;
            if (current && posted.has(current)) {
                const target = `${window.location.pathname}${window.location.search}#comments`;
                window.Turbo ? window.Turbo.visit(target, { action: 'replace' }) : window.location.assign(target);
            }
        });
    }

    disconnect() {
        if (this.onTileClick) {
            this.element.removeEventListener('click', this.onTileClick);
            this.onTileClick = null;
        }
        this.lightbox?.destroy();
        this.lightbox = null;
    }
}
