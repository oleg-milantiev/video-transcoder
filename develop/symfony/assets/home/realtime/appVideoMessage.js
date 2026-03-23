export function parseAppVideoMessage(message) {
    if (!message || typeof message !== 'object') {
        return null;
    }

    if (message.entity !== 'video') {
        return null;
    }

    if (!message.payload || typeof message.payload !== 'object') {
        return null;
    }

    return message.payload;
}

