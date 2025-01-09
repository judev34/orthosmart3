/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'overlay', 'content']
    static values = {
        open: { type: Boolean, default: true }
    }

    initialize() {
        // Initialize mediaQueries in the initialize lifecycle callback
        this.mediaQueries = {
            desktop: window.matchMedia('(min-width: 1024px)'),
            tablet: window.matchMedia('(min-width: 768px) and (max-width: 1023px)'),
            landscape: window.matchMedia('(orientation: landscape)')
        };

        // Bind the handler to preserve the context
        this.handleMediaQueryChange = this.checkScreenSize.bind(this);
    }

    connect() {
        // Add listeners after connection
        Object.values(this.mediaQueries).forEach(query => {
            query.addListener(this.handleMediaQueryChange);
        });

        // Initial check
        this.checkScreenSize();
    }

    disconnect() {
        // Remove listeners on disconnect
        if (this.mediaQueries) {
            Object.values(this.mediaQueries).forEach(query => {
                query.removeListener(this.handleMediaQueryChange);
            });
        }
    }

    checkScreenSize() {
        const { desktop, tablet, landscape } = this.mediaQueries;

        if (desktop.matches) {
            // Desktop : toujours ouvert
            this.openValue = true;
            this.sidebarTarget.classList.remove('-translate-x-full');
        } else if (tablet.matches && landscape.matches) {
            // Tablette en paysage : ouvert
            this.openValue = true;
            this.sidebarTarget.classList.remove('-translate-x-full');
        } else {
            // Tablette en portrait ou mobile : fermé
            this.openValue = false;
            this.sidebarTarget.classList.add('-translate-x-full');
        }

        this.updateSidebarState();
    }

    toggle() {
        this.openValue = !this.openValue;
        this.updateSidebarState();
    }

    updateSidebarState() {
        if (this.openValue) {
            this.sidebarTarget.classList.remove('-translate-x-full');
            this.contentTarget.classList.remove('ml-0');
            this.contentTarget.classList.add('ml-64');
            if (!this.mediaQueries.desktop.matches) {
                this.overlayTarget.classList.remove('hidden');
            }
        } else {
            this.sidebarTarget.classList.add('-translate-x-full');
            this.contentTarget.classList.remove('ml-64');
            this.contentTarget.classList.add('ml-0');
            this.overlayTarget.classList.add('hidden');
        }
    }

    closeOnOverlayClick() {
        if (!this.mediaQueries.desktop.matches) {
            this.openValue = false;
            this.updateSidebarState();
        }
    }
}
