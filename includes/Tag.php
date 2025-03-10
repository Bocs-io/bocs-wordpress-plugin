<?php

/**
 * class that handles everything related to tags
 *
 * @
 */
class Tag {

	/**
	 * gets the list of the product tags
	 *
	 * @return array|boolean
	 */
	public function get_product_tags(){
		$result = false;

		// get the list of the product tags on this site
		$tags = get_terms('product_tag');

		if (!empty($tags) && ! is_wp_error($tags)){

			$result = array();

			foreach ($tags as $tag){
				$result[] = $tag->name;
			}
		}

		return $result;
	}

    /**
     *
     * Overrides the `saved_term` WP Hook
     * @see wp-includes/taxonomy.php
     *
     * @param int    $term_id  Term ID.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * @param bool   $update   Whether this is an existing term being updated.
     * @param array  $args     Arguments passed to wp_insert_term().
     *
     * @return void
     */
	public function bocs_saved_term($term_id, $tt_id, $taxonomy, $update = false, $args){

        // we will not do the sync this is not a product tag
        if ($taxonomy !== 'product_tag') return;

        // we will get the term/slug
        $name = $args['name'];
        $slug = $args['slug'];

        // check first if the slug exists on bocs
        
	}

}