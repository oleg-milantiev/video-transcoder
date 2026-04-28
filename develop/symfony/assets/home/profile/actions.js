import { authFetch } from '../apiAuth.js';
import { extractApiErrorMessage, parseJsonResponse } from '../shared.js';
import { ROUTE_PROFILE } from '../routes.js';

export function createProfileActions(params) {
    const { config, profileState } = params;

    async function fetchProfile() {
        profileState.loading.value = true;
        profileState.error.value = '';

        try {
            const response = await authFetch(ROUTE_PROFILE, {
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
