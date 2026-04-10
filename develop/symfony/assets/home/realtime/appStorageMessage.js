export function parseAppStorageMessage(message) {
    if (!message || typeof message !== 'object') {
        return null;
    }

    if (message.entity !== 'storage') {
        return null;
    }

    if (!message.payload || typeof message.payload !== 'object') {
        return null;
    }

    return message.payload;
}
