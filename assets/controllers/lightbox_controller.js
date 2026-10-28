import { Controller } from '@hotwired/stimulus';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/dist/photoswipe.css';

/*
 * Visionneuse plein écran des photos (PhotoSwipe 5, licence MIT) : glisser d'une photo à l'autre, pincer ou
 * double-toucher pour zoomer, flèches du clavier.
 *
 * Sur l'élément qui porte data-controller="lightbox", chaque lien <a data-pswp-src data-pswp-width data-pswp-height>
 * devient une photo de la visionneuse ; son href (la fiche du média) reste le lien suivi sans JavaScript.
 * Les autres liens (vidéos, musique) ne sont pas concernés.
 */
export default class extends Controller {
    connect() {
        this.lightbox = new PhotoSwipeLightbox({
            gallery: this.element,
            children: 'a[data-pswp-src]',
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

        // Bouton « fiche » : ouvre la page du média (infos, petits mots, téléchargement)
        this.lightbox.on('uiRegister', () => {
            this.lightbox.pswp.ui.registerElement({
                name: 'media-page',
                order: 9,
                isButton: true,
                tagName: 'a',
                title: 'Voir la fiche et les petits mots',
                html: '<span class="pswp-media-page" aria-hidden="true">💬</span>',
                onInit: (element, pswp) => {
                    const update = () => {
                        const link = pswp.currSlide?.data.element;
                        element.href = link?.getAttribute('href') ?? '#';
                        element.hidden = !link || link.dataset.lightboxCurrent !== undefined;
                    };
                    pswp.on('change', update);
                    update();
                },
            });
        });

        this.lightbox.init();
    }

    disconnect() {
        this.lightbox?.destroy();
        this.lightbox = null;
    }
}
