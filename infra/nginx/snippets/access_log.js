function ipv4Octets(value) {
    const octets = value.split('.');
    if (octets.length !== 4 || !octets.every(octet => /^\d{1,3}$/.test(octet) && Number(octet) <= 255)) {
        return null;
    }
    return octets.map(Number);
}

function ipv6Groups(value) {
    if (value.includes('.')) {
        const separator = value.lastIndexOf(':');
        const octets = ipv4Octets(value.slice(separator + 1));
        if (separator < 0 || !octets) {
            return null;
        }
        const high = (octets[0] * 256 + octets[1]).toString(16);
        const low = (octets[2] * 256 + octets[3]).toString(16);
        value = value.slice(0, separator + 1) + high + ':' + low;
    }

    const halves = value.split('::');
    if (halves.length > 2) {
        return null;
    }
    const left = halves[0] ? halves[0].split(':') : [];
    const right = halves.length === 2 && halves[1] ? halves[1].split(':') : [];
    const count = left.length + right.length;
    if ((halves.length === 1 ? count !== 8 : count >= 8) ||
        !left.concat(right).every(group => /^[\da-f]{1,4}$/i.test(group))) {
        return null;
    }

    const groups = left.concat(Array(8 - count).fill('0'), right);
    return groups.map(group => parseInt(group, 16));
}

function prefix(value) {
    const ip = value.trim();
    if (!ip.includes(':')) {
        const octets = ipv4Octets(ip);
        return octets ? octets.slice(0, 3).join('.') + '.0' : '-';
    }

    const groups = ipv6Groups(ip);
    if (!groups) {
        return '-';
    }
    if (groups.slice(0, 5).every(group => group === 0) && groups[5] === 0xffff) {
        return [groups[6] >> 8, groups[6] & 255, groups[7] >> 8, 0].join('.');
    }

    return groups.slice(0, 3).map(group => group.toString(16)).join(':') + '::/48';
}

function peerPrefix(request) {
    return prefix(request.variables.remote_addr);
}

function forwardedPrefixes(request) {
    const header = request.headersIn['X-Forwarded-For'];
    return header ? header.split(',').map(prefix).join(', ') : '-';
}

export default { peerPrefix, forwardedPrefixes };
