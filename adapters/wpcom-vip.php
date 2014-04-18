<?php

/**
 * An adapter for WordPress.com VIP
 */

class ES_WP_Query extends ES_WP_Query_Wrapper {
	protected function query_es( $es_args ) {
		if ( function_exists( 'es_api_search_index' ) ) {
			return es_api_search_index( $es_args );
		}
	}

	protected function set_posts( $q, $es_response ) {
		$this->posts = array();
		if ( isset( $es_response['results']['hits'] ) ) {
			switch ( $q['fields'] ) {
				case 'ids' :
					foreach ( $es_response['results']['hits'] as $hit ) {
						$this->posts[] = $hit['fields'][ $this->es_map( 'post_id' ) ];
					}
					return;

				case 'id=>parent' :
					foreach ( $es_response['results']['hits'] as $hit ) {
						$this->posts[ $hit['fields'][ $this->es_map( 'post_id' ) ] ] = $hit['fields'][ $this->es_map( 'post_parent' ) ];
					}
					return;

				default :
					if ( apply_filters( 'es_query_use_source', false ) ) {
						$this->posts = wp_list_pluck( $es_response['results']['hits'], '_source' );
						return;
					} else {
						$post_ids = array();
						foreach ( $es_response['results']['hits'] as $hit ) {
							$post_ids[] = absint( $hit['fields'][ $this->es_map( 'post_id' ) ] );
						}
						$post_ids = array_filter( $post_ids );
						if ( ! empty( $post_ids ) ) {
							global $wpdb;
							$post__in = implode( ',', $post_ids );
							$this->posts = $wpdb->get_results( "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE ID IN ($post__in) ORDER BY FIELD( {$wpdb->posts}.ID, $post__in )" );
						}
						return;
					}
			}
		} else {
			$this->posts = array();
		}
	}

	/**
	 * Set up the amount of found posts and the number of pages (if limit clause was used)
	 * for the current query.
	 *
	 * @access public
	 */
	public function set_found_posts( $q, $es_response ) {
		if ( isset( $es_response['results']['total'] ) ) {
			$this->found_posts = absint( $es_response['results']['total'] );
		} else {
			$this->found_posts = 0;
		}
		$this->found_posts = apply_filters_ref_array( 'es_found_posts', array( $this->found_posts, &$this ) );
		$this->max_num_pages = ceil( $this->found_posts / $q['posts_per_page'] );
	}
}

function sp_es_field_map( $es_map ) {
	return wp_parse_args( array(
		'post_author'        => 'author_id',
		'post_date'          => 'date',
		'post_date_gmt'      => 'date_gmt',
		'post_content'       => 'content',
		'post_title'         => 'title',
		'post_excerpt'       => 'excerpt',
		'post_password'      => 'post_password',  // this isn't indexed on vip
		'post_name'          => 'post_name',      // this isn't indexed on vip
		'post_modified'      => 'modified',
		'post_modified_gmt'  => 'modified_gmt',
		'post_parent'        => 'parent_post_id',
		'menu_order'         => 'menu_order',     // this isn't indexed on vip
		'post_mime_type'     => 'post_mime_type', // this isn't indexed on vip
		'comment_count'      => 'comment_count',  // this isn't indexed on vip
		'post_meta'          => 'meta.%s.raw',
		'post_meta.analyzed' => 'meta.%s',
		'term_id'            => 'taxonomy.%s.term_id',
		'term_slug'          => 'taxonomy.%s.slug',
		'term_name'          => 'taxonomy.%s.name.raw',
		'category_id'        => 'category.term_id',
		'category_slug'      => 'category.slug',
		'category_name'      => 'category.name.raw',
		'tag_id'             => 'tag.term_id',
		'tag_slug'           => 'tag.slug',
		'tag_name'           => 'tag.name.raw',
	), $es_map );
}
add_filter( 'es_field_map', 'sp_es_field_map' );