import { Controller } from '@hotwired/stimulus';

/*
 * Fiche média : passer au souvenir précédent / suivant
 * avec les flèches du clavier ou en glissant le doigt sur une tablette.
 */
export default class extends Controller {
    static targets = ['previous', 'next'];

    connect() {
        this.onKeydown = this.onKeydown.bind(this);
        this.onTouchStart = this.onTouchStart.bind(this);
        this.onTouchEnd = this.onTouchEnd.bind(this);
        document.addEventListener('keydown', this.onKeydown);
        this.element.addEventListener('touchstart', this.onTouchStart, { passive: true });
        this.element.addEventListener('touchend', this.onTouchEnd, { passive: true });
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKeydown);
        this.element.removeEventListener('touchstart', this.onTouchStart);
        this.element.removeEventListener('touchend', this.onTouchEnd);
    }

    onKeydown(event) {
        // Les flèches servent déjà à avancer dans une vidéo, à saisir du texte ou à changer de photo dans la visionneuse
        if (this.isInteractive(event.target) || this.lightboxIsOpen() || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }
        if (event.key === 'ArrowLeft') {
            this.go('previous');
        } else if (event.key === 'ArrowRight') {
            this.go('next');
        }
    }

    onTouchStart(event) {
        this.touchStart = this.isInteractive(event.target) ? null : event.changedTouches[0];
    }

    onTouchEnd(event) {
        if (!this.touchStart) {
            return;
        }
        const deltaX = event.changedTouches[0].clientX - this.touchStart.clientX;
        const deltaY = event.changedTouches[0].clientY - this.touchStart.clientY;
        this.touchStart = null;

        // Glissement nettement horizontal, pour ne pas gêner le défilement de la page
        if (Math.abs(deltaX) > 60 && Math.abs(deltaX) > 2 * Math.abs(deltaY)) {
            this.go(deltaX > 0 ? 'previous' : 'next');
        }
    }

    go(direction) {
        const hasTarget = direction === 'previous' ? this.hasPreviousTarget : this.hasNextTarget;
        if (hasTarget) {
            this[`${direction}Target`].click();
        }
    }

    lightboxIsOpen() {
        return document.querySelector('.pswp') !== null;
    }

    isInteractive(element) {
        return element instanceof Element && element.closest('video, audio, input, textarea, select, [contenteditable]') !== null;
    }
}
