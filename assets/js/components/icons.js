const { createElement: el } = wp.element;

window.bocsIcons = {
    bocsIcon: el('svg', {
        width: 24,
        height: 24,
        viewBox: '0 0 24 24'
    }, el('path', {
        d: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z'
    })),

    bocsIconMedium: el('svg', {
        width: 100,
        height: 100,
        viewBox: '0 0 24 24',
        className: 'bocs-icon-medium'
    }, el('path', {
        d: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z'
    }))
};