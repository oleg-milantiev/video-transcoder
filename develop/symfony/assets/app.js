import './stimulus_bootstrap.js';
import './styles/app.css';
import { mountHomeSpa } from './home/mountHomeSpa.js';
import { bindFlashNotifications } from './flash/bindFlashNotifications.js';
import { initContactUs } from './home/contactUs.js';

function bootHomeSpa() {
    bindFlashNotifications();
    mountHomeSpa();
    if (typeof config !== 'undefined') {
        initContactUs(config);
    }
}

document.addEventListener('DOMContentLoaded', bootHomeSpa);
document.addEventListener('turbo:load', bootHomeSpa);
