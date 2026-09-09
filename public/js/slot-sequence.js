window.smashZoneConsecutiveSlots = function (slots) {
    const sorted = [...slots].sort((a, b) => a.start.localeCompare(b.start));
    return sorted.every((slot, index) => index === 0 || sorted[index - 1].end === slot.start);
};
