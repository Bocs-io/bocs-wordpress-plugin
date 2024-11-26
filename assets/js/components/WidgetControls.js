const { BlockControls, ToolbarGroup, DropdownMenu } = wp.blockEditor;

window.bocsComponents = window.bocsComponents || {};
window.bocsComponents.WidgetControls = function WidgetControls({ onSelect, widgetOptions, collectionOptions }) {
    return wp.element.createElement(BlockControls, 
        { key: 'controls' },
        wp.element.createElement(ToolbarGroup, null,
            wp.element.createElement(DropdownMenu, {
                label: 'Collections',
                text: 'Collections',
                controls: collectionOptions,
                isTertiary: true,
                icon: false,
                className: 'bocs-dropdown-menu'
            }),
            wp.element.createElement(DropdownMenu, {
                label: 'Widgets',
                text: 'Widgets',
                controls: widgetOptions,
                isTertiary: true,
                icon: false,
                className: 'bocs-dropdown-menu'
            })
        )
    );
}; 