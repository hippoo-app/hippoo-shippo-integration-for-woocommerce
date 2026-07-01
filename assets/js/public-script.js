jQuery(function ( $ ) {

	$(document).on('click', '.shippo-show-history', function(e){
		e.preventDefault();
		let $link = $(this);
		let $historyBox = $('.shippo-history-box');
		$historyBox.slideToggle(200);
		$link.toggleClass('open');
	});

});