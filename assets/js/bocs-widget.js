const bocsEl = wp.element.createElement;

const bocsIcon =
	bocsEl(
		'svg',
		{ width: 20, height: 20, viewBox: "0 0 36 36", version: 1.1, xmlns: "http://www.w3.org/2000/svg", xmlnsXlink:"http://www.w3.org/1999/xlink", xmlSpace:"preserve", xmlnsSerif: "http://www.serif.com/", style: {
			fillRule: "evenodd", clipRule: "evenodd", strokeLinejoin: "round", strokeMiterlimit: 2
			} },
		bocsEl(
			'g',
			{ transform: "matrix(1,0,0,1,-647.753,-303.839)"},
			bocsEl(
				'g',
				{transform: "matrix(1,0,0,1,-8.46249,-21.314)"},
				bocsEl(
					'path',
					{ d: "M686.684,328.173C686.258,326.125 684.248,324.807 682.199,325.234L659.235,330.012C657.186,330.439 655.869,332.448 656.295,334.497L661.074,357.461C661.5,359.51 663.51,360.827 665.558,360.401L688.523,355.622C690.572,355.196 691.889,353.187 691.463,351.138L686.684,328.173ZM673.879,334.202C678.21,334.202 681.726,338.062 681.726,342.817C681.726,347.572 678.21,351.432 673.879,351.432C669.548,351.432 666.032,347.572 666.032,342.817C666.032,338.062 669.548,334.202 673.879,334.202Z", style: {
						fill: "#00848B"
						}}
				)
			)
		)
);

let collectionsList = [];
let bocsList = [];

let collectionOptions = [];
let bocsOptions = [];

jQuery( async function ($){

	try {

		collectionsList = $.ajax({
			url: ajax_object.collectionsURL,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			headers: {
				'Organization': ajax_object.Organization,
				'Store': ajax_object.Store,
				'Authorization': ajax_object.Authorization
			}
		});

		bocsList = $.ajax({
			url: ajax_object.bocsURL,
			type: "GET",
			contentType: "application/json; charset=utf-8",
			headers: {
				'Organization': ajax_object.Organization,
				'Store': ajax_object.Store,
				'Authorization': ajax_object.Authorization
			}
		});

		await collectionsList.then( (collections) => {
			collections.data.forEach( (collection) => {
				collectionOptions.push(
					{
						id: 'collection-'+collection.collectionId,
						name: collection.name == '' ? collection.collectionId : collection.name
					}
				);
			});
		});

		await bocsList.then( (bocs) => {
			bocs.data.forEach( (boc) => {
				bocsOptions.push(
					{
						id: 'bocs-'+boc.bocsId,
						name: boc.name == '' ? boc.bocsId : boc.name
					}
				);
			});
		});

	} catch (error){
		console.log(error);
		options = [React.createElement(
			"option",
			null,
			"No store connected"
		)];
	}

});

wp.blocks.registerBlockType('woocommerce-bocs/bocs-widget', {
	title: 'Bocs Widget',
	icon: bocsIcon,
	category: 'widgets',
	attributes: {
		collectionId: { type: 'string' }
	},
	edit: function (props){

		function updateCollectionId(event){

			props.setAttributes({collectionId: ""});

			if (event.target.parentElement.querySelector(".bocsNested") ){
				event.target.parentElement.querySelector(".bocsNested").classList.toggle("active");
			} else {
				props.setAttributes({collectionId: event.target.id});
				event.toggle("active");
			}

		}

		let collectionHTML = [];
		let bocsHTML = [];

		collectionOptions.forEach((collection) => {
			collectionHTML.push(
				React.createElement("li", {
					onClick: updateCollectionId,
					id: collection.id
				}, collection.name)
			);
		});

		bocsOptions.forEach((bocs) => {
			bocsHTML.push(
				React.createElement("li", {
					onClick: updateCollectionId,
					id: bocs.id
				}, bocs.name)
			);
		});

		let result = "No Bocs or Collection Found";

		if (collectionOptions.length > 0 || bocsOptions.length > 0){
			result = /*#__PURE__*/React.createElement("ul", {
				id: "bocsUL"
			}, /*#__PURE__*/React.createElement("li", null, /*#__PURE__*/React.createElement("span", {
				class: "bocsCaret",
				onClick: updateCollectionId
			}, "Collected Widget"), /*#__PURE__*/React.createElement("ul", {
				class: "bocsNested"
			}, ...collectionHTML)), /*#__PURE__*/React.createElement("li", null, /*#__PURE__*/React.createElement("span", {
				class: "bocsCaret",
				onClick: updateCollectionId
			}, "Bocs Widget"), /*#__PURE__*/React.createElement("ul", {
				class: "bocsNested"
			}, ...bocsHTML)));
		}

		return result;

	},
	save: function (props){

		let result = "";

		if (props.attributes.collectionId){
			if (props.attributes.collectionId.includes('bocs-')){
				result = /*#__PURE__*/React.createElement("div", {
					id: "bocs-widget",
					"data-id": props.attributes.collectionId.replace("bocs-","")
				});
			} else if(props.attributes.collectionId.includes('collection-')){
				result =  /*#__PURE__*/React.createElement("div", {
					id: "collections-widget",
					"data-id": props.attributes.collectionId.replace("collection-","")
				});
			}
		}

		return result;

	}
});