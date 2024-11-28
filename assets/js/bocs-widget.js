/**
 * Bocs Widget Gutenberg Block
 * 
 * @file Implements a custom Gutenberg block for displaying Bocs widgets and collections
 * in WordPress posts and pages.
 * 
 * @requires wp.element
 * @requires wp.blockEditor
 * @requires wp.components
 * @requires jQuery
 * 
 * @typedef {Object} BocsWidgetObject
 * @property {string} widgetsURL - API endpoint for fetching widgets
 * @property {string} collectionsURL - API endpoint for fetching collections
 * @property {string} Organization - Organization identifier for API requests
 * @property {string} Store - Store identifier for API requests
 * @property {string} Authorization - Authorization token for API requests
 * @property {string} ajax_url - WordPress AJAX URL
 * @property {string} nonce - WordPress security nonce
 * @property {string} selected_id - Currently selected widget/collection ID
 * @property {string} selected_name - Currently selected widget/collection name
 * @property {string} dataURL - URL for widget data
 * 
 * @global {BocsWidgetObject} bocs_widget_object - Global object containing configuration and state
 * 
 * Features:
 * - Displays Bocs widgets and collections in WordPress posts/pages
 * - Provides dropdown menus for selecting widgets and collections
 * - Supports real-time preview in the Gutenberg editor
 * - Persists widget/collection selection via WordPress AJAX
 * - Includes custom SVG icons for the block
 * 
 * Usage:
 * 1. Add the block to a post/page using the Gutenberg editor
 * 2. Select either a widget or collection from the dropdown menus
 * 3. Save the post/page to display the selected widget/collection
 * 
 * @since 1.0.0
 */

/**
 * WordPress core dependencies for creating elements and accessing block editor functionality
 * @const {Function} createElement - WordPress element creation function, aliased as 'el'
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/packages/packages-element/}
 */
const { createElement: el } = wp.element;

/**
 * Block editor components for handling block controls and properties
 * @const {Object} BlockControls - Component for adding controls to block toolbar
 * @const {Function} useBlockProps - Hook to get props for the block wrapper element
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/}
 */
const { BlockControls, useBlockProps } = wp.blockEditor;

/**
 * WordPress components for building the user interface
 * @const {Object} ToolbarGroup - Container for grouping toolbar controls
 * @const {Object} DropdownMenu - Component for creating dropdown menus
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/components/}
 */
const { ToolbarGroup, DropdownMenu } = wp.components;

/**
 * SVG Icon Definitions
 * These icons are used to represent the Bocs Widget in different contexts
 */

// Small icon (24x24) used in block inserter and toolbar
const bocsIcon = el(
	'svg',
	{
		width: 24, height: 24, viewBox: "0 0 36 36", xmlns: "http://www.w3.org/2000/svg",
		style: {
			fillRule: "evenodd", clipRule: "evenodd", strokeLinejoin: "round", strokeMiterlimit: 2
		}
	},
	el(
		'g',
		{ transform: "matrix(1,0,0,1,-647.753,-303.839)" },
		el(
			'g',
			{ transform: "matrix(1,0,0,1,-8.46249,-21.314)" },
			el(
				'path',
				{
					d: "M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z",
					style: { fill: "#00848B" }
				}
			)
		)
	)
);

// Medium icon (50x50) used in block preview and edit interface
const bocsIconMedium = el(
	'svg',
	{
		width: 50, height: 50, viewBox: "0 0 50 50", xmlns: "http://www.w3.org/2000/svg",
		style: {
			fillRule: "evenodd", clipRule: "evenodd", strokeLinejoin: "round", strokeMiterlimit: 2
		}
	},
	el(
		'g',
		{ transform: "matrix(1,0,0,1,-647.753,-303.839)" },
		el(
			'g',
			{ transform: "matrix(1,0,0,1,-8.46249,-21.314)" },
			el(
				'path',
				{
					d: "M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z",
					style: { fill: "#00848B" }
				}
			)
		)
	)
);

/**
 * Global State Management
 * Initialize arrays to store widget and collection options fetched from the API.
 * These arrays act as a cache for the dropdown menu options and are populated
 * when the page loads. The initial "Please wait..." message provides feedback
 * to users while data is being fetched.
 */
let widgetsOptions = [{ id: 'widget-0', name: "Please wait..." }];
let collectionsOptions = [{ id: 'collection-0', name: "Please wait..." }];

/**
 * Data Fetching
 * Initializes the widget by fetching available widgets and collections when the page loads.
 * Uses jQuery's AJAX functionality to make API calls with proper authentication headers.
 * The fetched data is transformed into a format suitable for dropdown menu options.
 */
jQuery(async function ($) {

	// Fetch widgets list
	try {
		const widgetsList = await $.ajax({
			url: bocs_widget_object.widgetsURL,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			headers: {
				'Organization': bocs_widget_object.Organization,
				'Store': bocs_widget_object.Store,
				'Authorization': bocs_widget_object.Authorization
			}
		});
		
		// Transform API response into format needed for dropdown options
		widgetsOptions = widgetsList.data.data.map(widget => ({
			id: `widget-${widget.id}`,
			name: widget.title || widget.id // Fallback to ID if title is not available
		}));

	} catch (error) {
		console.error('Failed to fetch widgets:', error);
	}

	try {
		const collectionsList = await $.ajax({
			url: bocs_widget_object.collectionsURL,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			headers: {
				'Organization': bocs_widget_object.Organization,
				'Store': bocs_widget_object.Store,
				'Authorization': bocs_widget_object.Authorization
			}
		});

		// Map the retrieved collections data to the format required for the options.
		collectionsOptions = collectionsList.data.data.map(collection => ({
			id: `collection-${collection.id}`,
			name: collection.title || collection.id
		}));

	} catch (error) {
		// Log any errors that occur during the AJAX call.
		console.error(error);
	}
});

/**
 * Block Registration
 * Register the Bocs Widget block type with WordPress
 */
/**
 * Register the Bocs Widget block type
 * @see {@link https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/}
 * 
 * @typedef {Object} BlockConfig
 * @property {string} title - The display title of the block
 * @property {Element} icon - The block's icon
 * @property {string} category - The block category (e.g., 'widgets')
 * @property {Object} attributes - Block attributes configuration
 * @property {string} description - Block description
 * @property {Function} edit - Component for the editor interface
 * @property {Function} save - Component for saving block content
 * 
 * @type {BlockConfig}
 */
wp.blocks.registerBlockType('woocommerce-bocs/bocs-widget', {
	title: 'Bocs Widget',
	icon: bocsIcon,
	category: 'widgets',
	attributes: {
		widgetId: { 
			type: 'string',
			description: 'Stores the selected widget or collection ID'
		}
	},
	description: "This block displays products from your store using Bocs widgets or collections",
	
	/**
	 * Edit Component
	 * Renders the block's edit interface in the Gutenberg editor
	 * 
	 * @param {Object} props Block properties including attributes and setAttributes
	 * @returns {Element} Block edit interface
	 */
	edit: function (props) {
		const blockProps = useBlockProps();
		const isSelected = props.isSelected;
		const preLoadText = bocs_widget_object.selected_id ? bocs_widget_object.selected_name : "";

		/**
		 * Updates the selected widget/collection and persists the selection
		 * 
		 * @param {string} id The ID of the selected widget/collection (format: 'widget-123' or 'collection-123')
		 * @param {string} name The display name of the selected widget/collection
		 * 
		 * This function:
		 * 1. Updates block attributes
		 * 2. Updates UI elements
		 * 3. Persists selection via AJAX to WordPress backend
		 */
		function updateSelected(id, name) {
			// Update the block attributes with the selected widget ID.
			props.setAttributes({ widgetId: id });

			// Hide dropdown menus and descriptions.
			jQuery('.bocs-dropdown-menu-body, .bocs-widget-description, .bocs-wrapper').hide();

			var type = 'widget';
			
			if (id.indexOf('collection') !== -1) {
				type = 'collection';
			}

			// Update the description with the selected widget name.
			jQuery('.bocs-widget-selected-desc').html(`<b>Widget:</b><span>Name: ${name}</span>`);
			if (type === 'collection') jQuery('.bocs-widget-selected-desc').html("<b>Collections Widget:</b><span>Name: " + name + "</span>");


			// Send the selected widget data to the server via AJAX.
			jQuery.ajax({
				url: bocs_widget_object.ajax_url,
				type: 'POST',
				data: {
					action: 'save_widget_options',
					nonce: bocs_widget_object.nonce,
					selectedOption: id,
					selectedOptionName: name,
					postId: wp.data.select('core/editor').getCurrentPostId()
				}
			});
		}

		/**
		 * Transform widget and collection data into dropdown menu options
		 * Each option includes:
		 * - label: Display text in dropdown
		 * - value: Unique identifier
		 * - title: Tooltip text
		 * - onClick: Handler for selection
		 */
		const widgetMenuOptions = widgetsOptions
			.filter(widget => widget.name || widget.title)
			.map(widget => {
				const displayName = widget.name || widget.title;
				return {
					label: displayName,
					value: widget.id,
					title: displayName,
					onClick: () => updateSelected(widget.id, displayName)
				};
			});

		// Map the collection options to be used in the dropdown menu.
		const collectionMenuOptions = collectionsOptions
			.filter(collection => collection.name || collection.title)
			.map(collection => {
				const displayName = collection.name || collection.title;
				return {
					label: displayName,
					value: collection.id,
					title: displayName,
					onClick: () => {
						console.log('Collection ID:', collection.id);
						updateSelected(collection.id, displayName)
					}
				};
			});

		// Return the block's edit interface.
		return el(
			"div",
			blockProps,
			isSelected && el(
				BlockControls,
				{ key: 'controls' },
				el(ToolbarGroup, null,
					el(DropdownMenu, {
						label: 'Collections',
						text: 'Collections',
						controls: collectionMenuOptions,
						isTertiary: true,
						icon: false,
						className: 'bocs-dropdown-menu'
					}),
					el(DropdownMenu, {
						label: 'Widgets',
						text: 'Widgets',
						controls: widgetMenuOptions,
						isTertiary: true,
						icon: false,
						className: 'bocs-dropdown-menu'
					})
				)
			),
			bocsIconMedium, // Display the medium-sized icon.
			!bocs_widget_object.selected_id && el(
				"p",
				{ className: 'bocs-widget-description' },
				"This block displays products from your Bocs widget. Click on Bocs or Collection to add the code to display your widget. Once added, save your page and you should be able to view the widget on your site now."
			),
			!bocs_widget_object.selected_id && isSelected && el(
				"div",
				{ className: "bocs-wrapper" },
				el(DropdownMenu, {
					label: 'Collections',
					text: 'Collections',
					controls: collectionMenuOptions,
					isTertiary: true,
					icon: false,
					className: 'bocs-dropdown-menu bocs-dropdown-menu-body'
				}),
				el(DropdownMenu, {
					label: 'Widgets',
					text: 'Widgets',
					controls: widgetMenuOptions,
					isTertiary: true,
					icon: false,
					className: 'bocs-dropdown-menu bocs-dropdown-menu-body'
				})
			),
			el(
				"p",
				{ className: 'bocs-widget-selected-desc' },
				preLoadText // Display the pre-loaded text if a widget is selected.
			)
		);
	},
	
	/**
	 * Save Component
	 * Defines how the block should be saved to post content.
	 * Processes the widget/collection ID to strip prefixes and
	 * creates a container div with necessary data attributes for
	 * frontend rendering.
	 * 
	 * @param {Object} props Block properties
	 * @returns {Element} Saved block content with data attributes for frontend processing
	 */
	save: function (props) {
		// Extract the numeric ID from the widget/collection ID string
		var dataId = '';
		if (props.attributes.widgetId) {
			dataId = props.attributes.widgetId
				.replace("widget-", "")
				.replace("collection-", "");
		} else if (props.attributes.collectionId) {
			dataId = props.attributes.collectionId.replace("collection-", "");
		}

		// Return the container div with necessary data attributes
		return el("div", {
			id: "bocs-widget",
			"data-id": dataId,
			"data-url": bocs_widget_object.dataURL
		});
	}
});
