import Swal from '../vendor/sweetalert2/sweetalert2.index.js';
import { parseJsonResponse, extractApiErrorMessage, normalizeErrorMessage } from './shared.js';

export async function openContactUsModal() {
    const { value: formValues } = await Swal.fire({
        title: 'Contact Us',
        html: `
            <div class="mb-3 text-start">
                <label class="form-label small fw-semibold">Your email</label>
                <input id="swal-contact-email" type="email" class="swal2-input" placeholder="your@email.com" style="margin:0;width:100%">
            </div>
            <div class="mt-2 text-start">
                <label class="form-label small fw-semibold">Your message</label>
                <textarea id="swal-contact-message" class="swal2-textarea" placeholder="Write your message here..." maxlength="1000" rows="5" style="margin:0;width:100%"></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send',
        preConfirm: () => {
            const email = document.getElementById('swal-contact-email').value.trim();
            const message = document.getElementById('swal-contact-message').value.trim();
            if (!email) {
                Swal.showValidationMessage('Email must not be empty');
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                Swal.showValidationMessage('Please enter a valid email address');
                return false;
            }
            if (!message) {
                Swal.showValidationMessage('Message must not be empty');
                return false;
            }
            return { email, message };
        },
    });

    if (!formValues) {
        return;
    }

    try {
        const response = await fetch('/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(formValues),
        });

        const payload = await parseJsonResponse(response);

        if (!response.ok) {
            const msg = extractApiErrorMessage(payload, 'Failed to send message');
            await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
            return;
        }

        await Swal.fire({
            title: 'Request received!',
            text: `We received your request and will contact you via email ${formValues.email} shortly.`,
            icon: 'success',
        });
    } catch (e) {
        const msg = normalizeErrorMessage(e, 'Failed to send message');
        await Swal.fire({ title: 'Error', text: msg, icon: 'error' });
    }
}

function bindContactTrigger(el) {
    if (!el || el.dataset.contactUsInit === '1') {
        return;
    }
    el.dataset.contactUsInit = '1';
    el.addEventListener('click', function (event) {
        event.preventDefault();
        void openContactUsModal();
    });
}

export function initContactUs() {
    bindContactTrigger(document.getElementById('footer-contact-us'));
    bindContactTrigger(document.getElementById('page-contact-us'));
}

