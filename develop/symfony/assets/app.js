import './stimulus_bootstrap.js';
import './styles/app.css';
import { mountHomeSpa } from './home/mountHomeSpa.js';

function bootHomeSpa() {
	mountHomeSpa();
}

document.addEventListener('DOMContentLoaded', bootHomeSpa);
document.addEventListener('turbo:load', bootHomeSpa);
