window.bocsApi = {
    fetchWidgets: async () => {
        try {
            const response = await jQuery.ajax({
                url: bocs_widget_object.widgetsURL,
                type: "GET",
                contentType: "application/json; charset=utf-8",
                headers: {
                    'Organization': bocs_widget_object.Organization,
                    'Store': bocs_widget_object.Store,
                    'Authorization': bocs_widget_object.Authorization
                }
            });
            
            return response.data.data.map(widget => ({
                id: `widget-${widget.id}`,
                name: widget.title || widget.id
            }));
        } catch (error) {
            console.error('Failed to fetch widgets:', error);
            return [];
        }
    },

    fetchCollections: async () => {
        try {
            const response = await jQuery.ajax({
                url: bocs_widget_object.collectionsURL,
                type: "GET",
                contentType: "application/json; charset=utf-8",
                headers: {
                    'Organization': bocs_widget_object.Organization,
                    'Store': bocs_widget_object.Store,
                    'Authorization': bocs_widget_object.Authorization
                }
            });
            
            return response.data.data.map(collection => ({
                id: `collection-${collection.id}`,
                name: collection.title || collection.id
            }));
        } catch (error) {
            console.error('Failed to fetch collections:', error);
            return [];
        }
    },

    saveWidgetSelection: async (id, name) => {
        return jQuery.ajax({
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
}; 