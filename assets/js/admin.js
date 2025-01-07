/**
 * Global BOCS configuration object injected by WordPress
 * @typedef {Object} bocsAjaxObject
 * @property {string} widgetsURL - Base URL for the BOCS widgets API
 * @property {string} Organization - Organization identifier for API requests
 * @property {string} Store - Store identifier for API requests
 * @property {string} Authorization - Authorization token for API requests
 * @property {Array<Object>} [bocs_collections] - Optional pre-loaded collection data
 * @property {string} bocs_collections[].id - Collection identifier
 * @property {string} bocs_collections[].title - Collection title
 * @property {string} [bocs_collections[].name] - Optional collection name
 */
/* global bocsAjaxObject */


jQuery(function ($) {

    /**
     * Handles URL redirection based on the selected source filter
     * Used to filter content between BOCS, WordPress, or both sources
     * @param {string} selectedSource - The source to redirect to ('bocs', 'wordpress', or 'both')
     */
    function redirectToSource(selectedSource) {
        // Construct base URL without query parameters
        const baseUrl = window.location.origin + window.location.pathname;
        // If 'both' is selected, use base URL, otherwise append source parameter
        window.location.href = selectedSource === 'both' 
            ? baseUrl 
            : `${baseUrl}?source=${selectedSource}`;
    }

    // Initialize DOM elements for source filtering
    const $sourceSelect = $('select#source');
    const $changeButton = $('input#changeit');

    // Set up event handlers for source selection
    if ($sourceSelect.length > 0) {
        if ($changeButton.length > 0) {
            // If change button exists, handle click events
            $changeButton.click(function(e) {
                const selectedSource = $sourceSelect.val();
                // Validate selected source before redirecting
                if (selectedSource && ['bocs', 'wordpress', 'both'].includes(selectedSource)) {
                    e.preventDefault();
                    redirectToSource(selectedSource);
                }
            });
        } else {
            // If no change button, handle direct select changes
            $sourceSelect.change(function(e) {
                e.preventDefault();
                redirectToSource($(this).val());
            });
        }
    }
});

/**
 * Makes an API call to the BOCS backend to fetch widget data
 * @param {string} url - The endpoint to query (e.g., 'collection' or 'bocs')
 * @returns {Promise<Object>} The API response containing widget data
 * @throws Will throw an error if the API call fails
 */
async function fetchBocsData(url) {
    return jQuery.ajax({
        url: `${bocsAjaxObject.widgetsURL}/?query=widgetType:${url}`,
        type: "GET",
        contentType: "application/json; charset=utf-8",
        headers: {
            'Organization': bocsAjaxObject.Organization,
            'Store': bocsAjaxObject.Store,
            'Authorization': bocsAjaxObject.Authorization
        }
    });
}

/**
 * Populates a select element with options based on provided data
 * Handles empty states and standardizes option text/value creation
 * @param {jQuery} $select - jQuery select element to populate
 * @param {Array} data - Array of objects containing option data
 * @param {string} [noDataMessage='No items...'] - Message to display when no data is available
 */
function populateSelect($select, data, noDataMessage = 'No items...') {
    // Clear existing options
    $select.empty();
    
    // Handle empty or invalid data
    if (!data || data.length === 0) {
        $select.append(new Option(noDataMessage, ''));
        return;
    }

    // Add default option
    $select.append(new Option('Please select...', ''));
    // Create options from data, using title, name, or id as display text
    data.forEach(item => {
        $select.append(new Option(
            item.title || item.name || item.id,
            item.id
        ));
    });
}

/**
 * Updates the shortcode display element with the selected widget ID
 * Generates a BOCS shortcode in the format [bocs widget='widget_id']
 * @param {string} value - The widget ID to include in the shortcode
 */
function updateShortcode(value) {
    const $shortcodeCopy = jQuery('#bocs-shortcode-copy');
    $shortcodeCopy.html(value ? `[bocs widget='${value}']` : '');
}

/**
 * Main initialization function for the BOCS sidebar
 * Sets up the collections and widgets dropdowns with data from the BOCS API
 * Implements mutual exclusivity between collections and widgets selection
 * @throws Will log an error if API calls or initialization fails
 */
jQuery(window).on("load", async function() {
    const $sidebar = jQuery("#bocs-page-sidebar");
    if (!$sidebar.length) return;

    try {
        // Initialize select elements
        const $collectionsSelect = jQuery('#bocs-page-sidebar-collections');
        const $widgetsSelect = jQuery('#bocs-page-sidebar-widgets');

        // Show loading state
        [$collectionsSelect, $widgetsSelect].forEach($select => {
            $select.append(new Option('Please wait...', ''));
        });

        // Load collections - either from pre-loaded data or API
        if (bocsAjaxObject.bocs_collections) {
            populateSelect($collectionsSelect, bocsAjaxObject.bocs_collections, 'No Collections...');
        } else {
            const collections = await fetchBocsData('collection');
            populateSelect($collectionsSelect, collections.data.data, 'No Collections...');
        }

        // Load widgets from API
        const widgets = await fetchBocsData('bocs');
        populateSelect($widgetsSelect, widgets.data.data, 'No Bocs...');

        // Set up mutual exclusivity between dropdowns
        [$collectionsSelect, $widgetsSelect].forEach($select => {
            $select.on('change', function() {
                // Reset other dropdown when this one changes
                const otherSelect = this === $collectionsSelect[0] ? $widgetsSelect : $collectionsSelect;
                if (this.value) {
                    otherSelect.prop('selectedIndex', 0);
                }
                updateShortcode(this.value);
            });
        });

    } catch (error) {
        console.error('Error initializing BOCS sidebar:', error);
    }
});
