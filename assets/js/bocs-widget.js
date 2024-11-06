const { createElement: el } = wp.element; // Alias wp.element.createElement to el for easier usage.
const { BlockControls, useBlockProps } = wp.blockEditor; // Destructure BlockControls and useBlockProps from wp.blockEditor.
const { ToolbarGroup, DropdownMenu } = wp.components; // Destructure ToolbarGroup and DropdownMenu from wp.components.

// Define the main icon for the block as an SVG element.
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

// Define a medium-sized icon for use elsewhere in the block.
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

// Initialize the widgets options with a default loading message.
let widgetsOptions = [{ id: 'widget-0', name: "Please wait..." }];

// Fetch widget options via AJAX and update widgetsOptions.
jQuery(async function ($) {
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

		// Map the retrieved widgets data to the format required for the options.
		widgetsOptions = widgetsList.data.data.map(widget => ({
			id: `widget-${widget.id}`,
			name: widget.title || widget.id
		}));

	} catch (error) {
		// Log any errors that occur during the AJAX call.
		console.error(error);
	}
});

// Register a new block type for the WooCommerce Bocs Widget.
wp.blocks.registerBlockType('woocommerce-bocs/bocs-widget', {
	title: 'Bocs Widget', // The title of the block.
	icon: bocsIcon, // The icon used for the block.
	category: 'widgets', // The category under which the block appears.
	attributes: {
		widgetId: { type: 'string' } // Attribute to store the selected widget ID.
	},
	description: "This block displays products from your store", // Block description.
	edit: function (props) {
		const blockProps = useBlockProps(); // Get block properties for the wrapper div.
		const isSelected = props.isSelected; // Check if the block is selected.
		const preLoadText = bocs_widget_object.selected_id ? bocs_widget_object.selected_name : ""; // Preload text if a widget is selected.

		// Function to handle updating the selected widget.
		function updateSelected(id, name) {
			// Update the block attributes with the selected widget ID.
			props.setAttributes({ widgetId: id });

			// Hide dropdown menus and descriptions.
			jQuery('.bocs-dropdown-menu-body, .bocs-widget-description, .bocs-wrapper').hide();

			// Update the description with the selected widget name.
			jQuery('.bocs-widget-selected-desc').html(`<b>Widget:</b><span>Name: ${name}</span>`);

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

		// Map the widgets options to be used in the dropdown menu.
		const widgetMenuOptions = widgetsOptions.map(widget => ({
			label: widget.name,
			value: widget.id,
			title: widget.name,
			onClick: () => updateSelected(widget.id, widget.name)
		}));

		// Return the block's edit interface.
		return el(
			"div",
			blockProps,
			isSelected && el(
				BlockControls,
				{ key: 'controls' },
				el(ToolbarGroup, null,
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
	save: function (props) {
		// Save the block's content with the selected widget ID.
		return props.attributes.widgetId ? el("div", {
			id: "bocs-widget",
			"data-id": props.attributes.widgetId.replace("widget-", ""),
			"data-url": bocs_widget_object.dataURL
		}) : null;
	}
});
