import './stimulus_bootstrap.js';
import './styles/app.css';
import { mountHomeSpa } from './home/mountHomeSpa.js';
import { initContactUs } from './home/contactUs.js';

function bootHomeSpa() {
    mountHomeSpa();
    initContactUs();
}

document.addEventListener('DOMContentLoaded', bootHomeSpa);
document.addEventListener('turbo:load', bootHomeSpa);
