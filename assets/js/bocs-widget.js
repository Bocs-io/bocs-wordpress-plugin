const el = wp.element.createElement;
const { BlockControls, useBlockProps } = wp.blockEditor;
const { Button, ToolbarGroup, DropdownMenu } = wp.components;

const bocsIcon = el(
	'svg',
	{
		width: 24, height: 24, viewBox: "0 0 36 36", version: "1.1", xmlns: "http://www.w3.org/2000/svg", xmlnsXlink: "http://www.w3.org/1999/xlink", xmlSpace: "preserve", xmlnsSerif: "http://www.serif.com/", style: {
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
					d: "M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z", style: {
						fill: "#00848B"
					}
				}
			)
		)
	)
);

const bocsIconMedium = el(
	'svg',
	{
		width: 50, height: 50, viewBox: "0 0 50 50", version: "1.1", xmlns: "http://www.w3.org/2000/svg", xmlnsXlink: "http://www.w3.org/1999/xlink", xmlSpace: "preserve", xmlnsSerif: "http://www.serif.com/", style: {
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
					d: "M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z", style: {
						fill: "#00848B"
					}
				}
			)
		)
	)
);

let widgetsOptions = [{ id: 'widget-0', name: "Please wait..." }];

jQuery(async function ($) {
	try {
		let widgetsList = await $.ajax({
			url: bocs_widget_object.widgetsURL,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			headers: {
				'Organization': bocs_widget_object.Organization,
				'Store': bocs_widget_object.Store,
				'Authorization': bocs_widget_object.Authorization
			}
		});

		widgetsOptions = widgetsList.data.map((widget) => ({
			id: 'widget-' + widget.id,
			name: widget.title || widget.id
		}));

		console.log('widgetsOptions', widgetsOptions);

	} catch (error) {
		console.error(error);
	}
});

wp.blocks.registerBlockType('woocommerce-bocs/bocs-widget', {
	title: 'Bocs Widget',
	icon: bocsIcon,
	category: 'widgets',
	attributes: {
		widgetId: { type: 'string' }
	},
	description: "This block displays products from your store",
	edit: function (props) {
		let blockProps = useBlockProps();
		let isSelected = props.isSelected;
		const hasSelected = bocs_widget_object.selected_id !== '';
		let preLoadText = hasSelected ? bocs_widget_object.selected_name : "";

		function updateSelected(id, name) {
			props.setAttributes({ widgetId: id });
			jQuery('.bocs-dropdown-menu-body').hide();
			jQuery('.bocs-widget-description').hide();
			jQuery('.bocs-wrapper').hide();
			jQuery('.bocs-widget-selected-desc').html(`<b>Widget:</b><span>Name: ${name}</span>`);

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

		let widgetMenuOptions = widgetsOptions.map((widget) => ({
			label: widget.name,
			value: widget.id,
			title: widget.name,
			onClick: () => updateSelected(widget.id, widget.name)
		}));

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
			bocsIconMedium,
			!hasSelected && el(
				"p",
				{ className: 'bocs-widget-description' },
				"This block displays products from your Bocs widget. Click on Bocs or Collection to add the code to display your widget. Once added, save your page and you should be able to view the widget on your site now."
			),
			!hasSelected && isSelected && el(
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
				preLoadText
			)
		);
	},
	save: function (props) {
		let result = "";
		if (props.attributes.widgetId) {
			result = el("div", {
				id: "bocs-widget",
				"data-id": props.attributes.widgetId.replace("widget-", "")
			});
		}

		return result;
	}
});
