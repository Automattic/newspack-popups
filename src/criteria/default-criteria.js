/* globals newspackPopupsCriteria */

( function () {
	if ( ! newspackPopupsCriteria?.config?.length ) {
		return;
	}

	const criteriaConfig = {
		articles_read: {
			matchingFunction: 'range',
			matchingAttribute: ras => {
				const views = ras.getUniqueActivitiesBy( 'article_view', 'post_id' );
				return views.filter( view => view.timestamp > Date.now() - 30 * 24 * 60 * 60 * 1000 );
			},
		},
		articles_read_in_session: {
			matchingFunction: 'range',
			matchingAttribute: ras => {
				const views = ras.getUniqueActivitiesBy( 'article_view', 'post_id' );
				return views.filter( view => view.timestamp > Date.now() - 45 * 60 * 1000 );
			},
		},
		favorite_categories: {
			matchingFunction: ( config, ras ) => {
				const views = ras.getUniqueActivitiesBy( 'article_view', 'post_id' );
				const categories = views.reduce( ( c, v ) => {
					if ( v.data?.categories?.length ) {
						c.push( ...v.data.categories );
					}
					return c;
				}, [] );
				const counts = categories.reduce( ( number, category ) => {
					number[ category ] = ( number[ category ] || 0 ) + 1;
					return number;
				}, {} );
				const countsArray = Object.entries( counts );
				countsArray.sort( ( a, b ) => b[ 1 ] - a[ 1 ] );
				/* TODO: Decide how to rank categories. */
				return false;
			},
		},
		newsletter: {
			matchingFunction: ( config, { store } ) => {
				switch ( config.value ) {
					case 1:
						return store.get( 'is_subscriber' );
					case 2:
						return ! store.get( 'is_subscriber' );
				}
			},
		},
		donation: {
			matchingFunction: ( config, { store } ) => {
				switch ( config.value ) {
					case 1:
						return store.get( 'is_donor' );
					case 2:
						return ! store.get( 'is_donor' );
					case 3:
						return store.get( 'is_former_donor' );
				}
			},
		},
		sources_to_match: {
			matchingFunction: 'list',
			matchingAttribute: ( { store } ) => {
				const value = document.referrer
					? ( new URL( document?.referrer ).hostname.replace( 'www.', '' ) || '' ).toLowerCase()
					: '';
				// Persist the referrer in the store.
				if ( value ) {
					store.set( 'referrer', value );
				}
				return store.get( 'referrer' );
			},
		},
		sources_to_exclude: {
			matchingFunction: ( config, { store } ) => {
				let list = config.value;
				if ( typeof list === 'string' ) {
					list = config.value.split( ',' ).map( item => item.trim() );
				}
				if ( ! Array.isArray( list ) || ! list.length ) {
					return true;
				}
				const value = store.get( 'referrer' );
				if ( ! value || ! list.includes( value ) ) {
					return true;
				}
				return false;
			},
			matchingAttribute: ( { store } ) => {
				const value = document.referrer
					? ( new URL( document?.referrer ).hostname.replace( 'www.', '' ) || '' ).toLowerCase()
					: '';
				// Persist the referrer in the store.
				if ( value ) {
					store.set( 'referrer', value );
				}
				return store.get( 'referrer' );
			},
		},
	};

	newspackPopupsCriteria.config.forEach( ( config, i ) => {
		if ( ! criteriaConfig[ config.id ] ) {
			return;
		}
		newspackPopupsCriteria.config[ i ] = {
			...config,
			...criteriaConfig[ config.id ],
		};
	} );
} )();
