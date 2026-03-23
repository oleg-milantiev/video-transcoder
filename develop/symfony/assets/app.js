import './stimulus_bootstrap.js';
import './styles/app.css';
import { mountHomeSpa } from './home/mountHomeSpa.js';
import { bindFlashNotifications } from './flash/bindFlashNotifications.js';

function bootHomeSpa() {
	bindFlashNotifications();
	mountHomeSpa();
}

document.addEventListener('DOMContentLoaded', bootHomeSpa);
document.addEventListener('turbo:load', bootHomeSpa);
