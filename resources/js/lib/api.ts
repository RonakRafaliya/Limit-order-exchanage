/**
 * API utility functions for making authenticated requests
 */

const getAuthToken = (): string | null => {
    return localStorage.getItem('auth_token');
};

const setAuthToken = (token: string): void => {
    localStorage.setItem('auth_token', token);
};

const removeAuthToken = (): void => {
    localStorage.removeItem('auth_token');
};

/**
 * Make an authenticated API request
 */
export const apiRequest = async (
    url: string,
    options: RequestInit = {}
): Promise<Response> => {
    const token = getAuthToken();

    const headers: HeadersInit = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...options.headers,
    };

    if (token) {
        // Coerce headers to a Record<string, string> for assignment
        (headers as Record<string, string>)['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`/api${url}`, {
        ...options,
        headers,
    });

    // Handle unauthorized responses (401) - redirect to login
    if (response.status === 401) {
        removeAuthToken();
        if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
            window.location.href = '/login';
        }
        throw new Error('Unauthorized');
    }

    return response;
};

/**
 * Logout function
 */
export const logout = async (): Promise<void> => {
    const token = getAuthToken();

    if (token) {
        try {
            await apiRequest('/logout', {
                method: 'POST',
            });
        } catch (error) {
            // Ignore errors during logout
            console.error('Logout error:', error);
        }
    }

    removeAuthToken();
    window.location.href = '/login';
};

export { getAuthToken, setAuthToken, removeAuthToken };

