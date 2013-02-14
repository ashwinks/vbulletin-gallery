var RedGallery = {

	page: 0,
	loading: null,
		
	init: function(){

		var $container = $('#gallery-container');
		$(window).load(function(){
			$container.imagesLoaded( function(){
				$container.masonry({
					itemSelector : '.image-item',
					columnWidth: 320,
					gutterWidth:0,
					isFitWidth: true
				});
			});
			$container.show();
			RedGallery.showLoading(false);
		});

	},
	
	showLoading: function(show, callback){
		if (show){
			$('#loading').show('fast', callback);
		}else{
			$('#loading').hide('fast', callback);
		}
	},

};
$(document).ready(function(){
	RedGallery.init();
});