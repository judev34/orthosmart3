/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

/*
 * This is component is responsible for handling the user dropdown menu
 * @stimulus-controller
 */
export default class extends Controller {
    static targets = [ "menu" ]

    connect() {
        // Initialize click outside listener
        this.clickOutsideHandler = this.handleClickOutside.bind(this);
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.menuTarget.classList.contains('hidden')) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.menuTarget.classList.remove('hidden');
        document.addEventListener('click', this.clickOutsideHandler);
    }

    close() {
        this.menuTarget.classList.add('hidden');
        document.removeEventListener('click', this.clickOutsideHandler);
    }

    handleClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    disconnect() {
        document.removeEventListener('click', this.clickOutsideHandler);
    }
}
