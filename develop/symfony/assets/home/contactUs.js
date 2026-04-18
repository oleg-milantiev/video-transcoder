import Swal from '../vendor/sweetalert2/sweetalert2.index.js';
import { authFetch } from './apiAuth.js';
import { parseJsonResponse, extractApiErrorMessage, normalizeErrorMessage } from './shared.js';

export async function openContactUsModal(config) {
    const { value: message } = await Swal.fire({
        title: 'Contact Us',
        input: 'textarea',
        inputLabel: 'Your message',
        inputPlaceholder: 'Write your message here...',
        inputAttributes: {
            maxlength: '1000',
            rows: '5',
        },
        showCancelButton: true,
        confirmButtonText: 'Send',
        preConfirm: (value) => {
            if (!value || String(value).trim() === '') {
                Swal.showValidationMessage('Message must not be empty');
                return false;
            }
            return String(value).trim();
        },
    });

    if (message === undefined || message === null) {
        return;
    }

    try {
        const response = await authFetch(config.route.contact, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message }),
        });

        const payload = await parseJsonResponse(response);

        if (!response.ok) {
            const msg = extractApiErrorMessage(payload, 'Failed to send message');
            await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
            return;
        }

        const email = config.user?.identifier ?? '';
        await Swal.fire({
            title: 'Request received!',
            text: `We received your request and will contact you via email ${email} shortly.`,
            icon: 'success',
        });
    } catch (e) {
        const msg = normalizeErrorMessage(e, 'Failed to send message');
        await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
    }
}

export function initContactUs(config) {
    const link = document.getElementById('footer-contact-us');
    if (!link) {
        return;
    }

    link.addEventListener('click', function (event) {
        event.preventDefault();
        void openContactUsModal(config);
    });
}
