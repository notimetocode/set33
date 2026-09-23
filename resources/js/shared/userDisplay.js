/**
 * @param {{ last_name?: string|null, first_name?: string|null, middle_name?: string|null, name?: string|null }|null|undefined} user
 */
export function formatUserFio(user) {
    if (!user) {
        return '';
    }

    const parts = [user.last_name, user.first_name, user.middle_name]
        .map((part) => (typeof part === 'string' ? part.trim() : ''))
        .filter(Boolean);

    if (parts.length > 0) {
        return parts.join(' ');
    }

    return typeof user.name === 'string' ? user.name.trim() : '';
}
