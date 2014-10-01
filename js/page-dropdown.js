/*global ajaxurl:false*/
( function( window, $ ) {
	var document = window.document;

	/* Our global plugin object */
	var CTPageDropdown = function() {
		var self = this;

		/* Initialize */
		self.init = function() {
			$('#parent_id').select2({
				minimumInputLength: 3,
				placeholder: '(no parent)',
				allowClear: true,
				ajax: {
					url: ajaxurl,
					dataType: 'json',
					data: self.ajaxData,
					results: self.ajaxResults
				},
				initSelection: self.initSelection,
				formatResult: self.formatResult,
				formatSelection: self.formatResult
			});
		};

		/* Used to preselect the post's parent if it's set, creating a result object
		that can be passed to the formatResult callback */
		self.initSelection = function(element, callback) {
			var id = $(element).val();
			if ( id !== '' ) {
				var data = {id: element.val(), title: element.attr('data-title')};
				callback(data);
			}
		};

		/* Select2 result formatter */
		self.formatResult = function(page) {
			return '<strong>' + page.title + '</strong><br><span class="page-dropdown-url">' + page.url + '</span>';
		};

		/* Select2 return the data required for the AJAX search query */
		self.ajaxData = function (term, page) {
			return {
				action: 'ct_page_query',
				ct_s: term,
				ct_page: page,
				ct_posts_per_page: 10,
				ct_post_id: $('#post_ID').val(),
				'_ajax_ct_page_dropdown_search': $('#_ajax_ct_page_dropdown_search').val()
			};
		};

		/* Select2 modify the results from AJAX call, enable infinite scroll */
		self.ajaxResults = function (result, page) {
			var more = (page * 10) < result.data.total;
			// custom formatting function takes care of massaging the JSON data
			return {results: result.data.pages, more: more};
		};
	};

	window.CTPageDropdown = new CTPageDropdown();

	$( document ).ready( window.CTPageDropdown.init );

} )( window, jQuery );
