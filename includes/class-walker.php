<?php
/**
 * Modified Page Dropdown walker
 */

class CT_Walker_PageDropdown extends Walker_PageDropdown
{
	protected static $walked_pages = array();

	function start_el( &$output, $page, $depth = 0, $args = array(), $id = 0 ) {
		$pad = str_repeat( '-&nbsp;', $depth );

		self::$walked_pages[] = array(
			'id' => $page->ID,
			'title' => $pad . trim( esc_html( strip_tags( get_the_title( $page ) ) ) ),
			'url' => trailingslashit( '/' . get_page_uri( $page->ID ) )
		);
	}

	public static function get_walked_pages() {
		return self::$walked_pages;
	}

}
