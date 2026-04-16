document.querySelectorAll( '.photo-strip-image' ).forEach( ( wrapper ) => {
	const img = wrapper.querySelector( 'img' );
	if ( ! img ) return;

	const markLoaded = () => wrapper.classList.add( 'is-loaded' );

	if ( img.complete && img.naturalWidth > 0 ) {
		markLoaded();
	} else {
		img.addEventListener( 'load', markLoaded );
	}
} );
