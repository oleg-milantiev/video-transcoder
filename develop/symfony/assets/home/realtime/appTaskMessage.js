export function parseAppTaskMessage(message) {
    if (!message || typeof message !== 'object') {
        return null;
    }

    if (message.entity !== 'task') {
        return null;
    }

    if (!message.payload || typeof message.payload !== 'object') {
        return null;
    }

    return message.payload;
}

