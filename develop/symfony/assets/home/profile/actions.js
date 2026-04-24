import { authFetch } from '../apiAuth.js';
import { extractApiErrorMessage, parseJsonResponse } from '../shared.js';

export function createProfileActions(params) {
    const { config, profileState } = params;

    async function fetchProfile() {
        profileState.loading.value = true;
        profileState.error.value = '';

        try {
            const profileUrl = config.route?.profile;
            if (!profileUrl) {
                profileState.error.value = 'Profile endpoint not configured.';
                return;
            }

            const response = await authFetch(profileUrl, {
                method: 'GET',
            });

            const payload = await parseJsonResponse(response);

            if (response.ok && payload && typeof payload === 'object') {
                profileState.dto.value = payload;
            } else {
                profileState.error.value = extractApiErrorMessage(payload) || 'Failed to load profile.';
            }
        } catch (err) {
            profileState.error.value = err instanceof Error ? err.message : 'Unknown error loading profile.';
        } finally {
            profileState.loading.value = false;
        }
    }

    return {
        fetchProfile,
    };
}
