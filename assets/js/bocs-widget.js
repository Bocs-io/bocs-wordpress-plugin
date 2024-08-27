const bocsEl = wp.element.createElement;
const blockEditor = wp.blockEditor;
const BlockControls = blockEditor.BlockControls;
const Components = wp.components;
const Button = Components.Button;
const Toolbar = Components.Toolbar;
const ToolbarButton = Components.ToolbarButton;
const ToolbarGroup = Components.ToolbarGroup;
const DropdownMenu = Components.DropdownMenu;

const bocsIcon =
	bocsEl(
		'svg',
		{
			width: 24, height: 24, viewBox: "0 0 36 36", version: 1.1, xmlns: "http://www.w3.org/2000/svg", xmlnsXlink: "http://www.w3.org/1999/xlink", xmlSpace: "preserve", xmlnsSerif: "http://www.serif.com/", style: {
				fillRule: "evenodd", clipRule: "evenodd", strokeLinejoin: "round", strokeMiterlimit: 2
			}
		},
		bocsEl(
			'g',
			{ transform: "matrix(1,0,0,1,-647.753,-303.839)" },
			bocsEl(
				'g',
				{ transform: "matrix(1,0,0,1,-8.46249,-21.314)" },
				bocsEl(
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

const bocsIconMedium =
	bocsEl(
		'svg',
		{
			width: 50, height: 50, viewBox: "0 0 50 50", version: 1.1, xmlns: "http://www.w3.org/2000/svg", xmlnsXlink: "http://www.w3.org/1999/xlink", xmlSpace: "preserve", xmlnsSerif: "http://www.serif.com/", style: {
				fillRule: "evenodd", clipRule: "evenodd", strokeLinejoin: "round", strokeMiterlimit: 2
			}
		},
		bocsEl(
			'g',
			{ transform: "matrix(1,0,0,1,-647.753,-303.839)" },
			bocsEl(
				'g',
				{ transform: "matrix(1,0,0,1,-8.46249,-21.314)" },
				bocsEl(
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

let collectionsList = [];
let bocsList = [];
let widgetList = [];

let collectionOptions = [{ id: 'collection-0', name: "Please wait..." }];
let bocsOptions = [{ id: 'bocs-0', name: "Please wait..." }];
let widgetOptions = [{ id: 'widget-0', name: "Please wait..." }];

jQuery(async function ($) {

	try {

		if (bocs_widget_object.bocs_collections) {

			collectionOptions = [];
			bocs_widget_object.bocs_collections.forEach((collection) => {
				collectionOptions.push(
					{
						id: 'collection-' + collection['id'],
						name: collection['name']
					}
				);
			});

		} else {

			collectionsList = $.ajax({
				url: bocs_widget_object.collectionsURL,
				type: "GET",
				contentType: "application/json; charset=utf-8",
				headers: {
					'Organization': bocs_widget_object.Organization,
					'Store': bocs_widget_object.Store,
					'Authorization': bocs_widget_object.Authorization
				}
			});

			await collectionsList.then((collections) => {
				collectionOptions = [];
				collections.data.forEach((collection) => {
					collectionOptions.push(
						{
							id: 'collection-' + collection.id,
							name: collection.name === '' ? collection.id : collection.name
						}
					);
				});
			});
		}

		if (bocs_widget_object.bocs_widgets) {

			bocsOptions = [];
			bocs_widget_object.bocs_widgets.forEach((bocs) => {
				bocsOptions.push(
					{
						id: 'bocs-' + bocs['id'],
						name: bocs['name']
					}
				);
			});

		} else {

			bocsList = $.ajax({
				url: bocs_widget_object.bocsURL,
				type: "GET",
				contentType: "application/json; charset=utf-8",
				headers: {
					'Organization': bocs_widget_object.Organization,
					'Store': bocs_widget_object.Store,
					'Authorization': bocs_widget_object.Authorization
				}
			});

			await bocsList.then((bocs) => {
				bocsOptions = [];
				bocs.data.forEach((boc) => {
					bocsOptions.push(
						{
							id: 'bocs-' + boc.id,
							name: boc.name === '' ? boc.id : boc.name
						}
					);
				});
			});
		}

		if (bocs_widget_object.bocs_widget_widgets) {
			widgetOptions = [];
			bocs_widget_object.bocs_widget_widgets.forEach((widget) => {
				widgetOptions.push(
					{
						id: 'widget-' + widget['id'],
						name: widget['name']
					}
				);
			});
		} else {
			widgetsList = $.ajax({
				url: bocs_widget_object.widgetsURL,
				type: "GET",
				contentType: "application/json; charset=utf-8",
				headers: {
					'Organization': bocs_widget_object.Organization,
					'Store': bocs_widget_object.Store,
					'Authorization': bocs_widget_object.Authorization
				}
			});

			await widgetsList.then((widgets) => {
				widgetOptions = [];
				widgets.data.forEach((widget) => {
					widgetOptions.push(
						{
							id: 'widget-' + widget.id,
							name: widget.name === '' ? widget.id : widget.name
						}
					);
				});
			});
		}
	} catch (error) {
		console.error(error);
	}

});

wp.blocks.registerBlockType('woocommerce-bocs/bocs-widget', {

	title: 'Bocs Widget',
	icon: bocsIcon,
	category: 'widgets',
	attributes: {
		collectionId: { type: 'string' }
	},
	description: "This block displays products from your ",
	edit: function (props) {

		let blockProps = blockEditor.useBlockProps();
		var isSelected = props.isSelected;

		const hasSelected = bocs_widget_object.selected_id !== '';
		let preLoadText = "";

		if (bocs_widget_object.selected_id !== '') {
			preLoadText = bocs_widget_object.selected_name;
		}

		function updateSelected(id, name) {

			props.setAttributes({ widgetId: id });

			// then update the view
			jQuery('div.bocs-dropdown-menu-body').hide();
			jQuery('p.bocs-widget-description').hide();
			jQuery('div.bocs-wrapper').hide();

			var type = 'widget';

			if (id.indexOf('collection') !== -1) {
				type = 'collection';
			} else if (id.indexOf('bocs') !== -1) {
				type = 'bocs';
			}

			if (type === 'bocs') jQuery('p.bocs-widget-selected-desc').html("<b>Bocs Widget:</b><span>Name: " + name + "</<span>");
			else if (type === 'collection') jQuery('p.bocs-widget-selected-desc').html("<b>Collections Widget:</b><span>Name: " + name + "</span>");
			else jQuery('p.bocs-widget-selected-desc').html("<b>Widget:</b><span>Name: " + name + "</span>");

			var params = {
				action: 'save_widget_options',
				nonce: bocs_widget_object.nonce,   // The AJAX nonce value
				selectedOption: id,
				selectedOptionName: name,
				postId: wp.data.select('core/editor').getCurrentPostId()
			};

			jQuery.ajax({
				url: bocs_widget_object.ajax_url,
				type: 'POST',
				data: params
			});

		}

		let collectionMenuOptions = [];
		let bocsMenuOptions = [];
		let widgetMenuOptions = [];

		collectionOptions.forEach((collection) => {
			collectionMenuOptions.push(
				{ label: collection.name, value: collection.id, title: collection.name, onClick: () => updateSelected(collection.id, collection.name) }
			);
		});

		bocsOptions.forEach((bocs) => {
			bocsMenuOptions.push(
				{ label: bocs.name, value: bocs.id, title: bocs.name, onClick: () => updateSelected(bocs.id, bocs.name) }
			);
		});

		widgetsOptions.forEach((widget) => {
			widgetMenuOptions.push(
				{ label: widget.name, value: widget.id, title: widget.name, onClick: () => updateSelected(widget.id, widget.name) }
			);
		});

		return bocsEl(
			"div",
			blockProps,
			isSelected && bocsEl(
				BlockControls,
				{ key: 'controls' },
				bocsEl(ToolbarGroup, null,
					bocsEl(
						DropdownMenu,
						{
							label: 'Collections',
							text: 'Collections',
							controls: collectionMenuOptions,
							isTertiary: true,
							icon: false,
							className: 'bocs-dropdown-menu'
						}
					),
					bocsEl(
						DropdownMenu,
						{
							label: 'Bocs',
							text: 'Bocs',
							controls: bocsMenuOptions,
							isTertiary: true,
							icon: false,
							className: 'bocs-dropdown-menu'
						}
					),
					bocsEl(
						DropdownMenu,
						{
							label: 'Widgets',
							text: 'Widgets',
							controls: widgetMenuOptions,
							isTertiary: true,
							icon: false,
							className: 'bocs-dropdown-menu'
						}
					)
				)
			),
			bocsIconMedium,
			!hasSelected && bocsEl(
				"p",
				{ className: 'bocs-widget-description' },
				"This block displays products from your Bocs widget. Click on Bocs or Collection to add the code to display your widget. Once added save your page and you should be able to view the widget on your site now."
			),
			!hasSelected && isSelected && bocsEl(
				"div",
				{ className: "bocs-wrapper" },
				bocsEl(
					DropdownMenu,
					{
						label: 'Collections',
						text: 'Collections',
						controls: collectionMenuOptions,
						isTertiary: true,
						icon: false,
						className: 'bocs-dropdown-menu bocs-dropdown-menu-body'
					}
				),
				bocsEl(
					DropdownMenu,
					{
						label: 'Bocs',
						text: 'Bocs',
						controls: bocsMenuOptions,
						isTertiary: true,
						icon: false,
						className: 'bocs-dropdown-menu bocs-dropdown-menu-body'
					}
				),
				bocsEl(
					DropdownMenu,
					{
						label: 'Widgets',
						text: 'Widgets',
						controls: widgetMenuOptions,
						isTertiary: true,
						icon: false,
						className: 'bocs-dropdown-menu bocs-dropdown-menu-body'
					}
				),
			),
			bocsEl(
				"p",
				{ className: 'bocs-widget-selected-desc' },
				preLoadText
			)
		);
	},
	save: function (props) {

		let result = "";

		// we will save first the list of the bocs and of the collections
		// on the database via options

		jQuery.ajax({
			url: bocs_widget_object.ajax_url,
			type: 'POST',
			data: {
				action: 'save_widget_options',
				nonce: bocs_widget_object.nonce,   // The AJAX nonce value
				selectedOption: props.attributes.collectionId
			}
		});


		if (props.attributes.collectionId) {
			if (props.attributes.collectionId.includes('bocs-')) {
				result = bocsEl("div", {
					id: "bocs-widget",
					"data-type": "bocs",
					"data-id": props.attributes.collectionId.replace("bocs-", "")
				});
			} else if (props.attributes.collectionId.includes('collection-')) {
				result = bocsEl("div", {
					id: "bocs-widget",
					"data-type": "collections",
					"data-id": props.attributes.collectionId.replace("collection-", "")
				});
			} else {
				result = bocsEl("div", {
					id: "bocs-widget",
					"data-id": props.attributes.widgetId.replace("widget-", "")
				});
			}
		}

		// we will save it to this post id's meta
		// this is forth the purpose of Editing


		return result;

	}
});
