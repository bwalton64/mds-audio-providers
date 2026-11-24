/**
 * MDS Audio Providers — WordPress 7.x React Admin UI.
 */
( function ( wp ) {
	'use strict';

	const { createElement: el, useState, useCallback, Component, Fragment } = wp.element;
	const {
		Button,
		Card,
		CardBody,
		CardHeader,
		Notice,
		SnackbarList,
		TabPanel,
		TextControl,
		ToggleControl,
	} = wp.components;
	const apiFetch = wp.apiFetch;
	const { __ } = wp.i18n;
	const speak = wp.a11y && wp.a11y.speak ? wp.a11y.speak : function () {};

	const boot = window.mdsAudioProvidersAdmin || {};
	const restBase = boot.restBase || '/mds-ap/v1';

	class AdminErrorBoundary extends Component {
		constructor( props ) {
			super( props );
			this.state = { hasError: false, message: '' };
		}
		static getDerivedStateFromError( err ) {
			return { hasError: true, message: err && err.message ? err.message : 'Unexpected UI error.' };
		}
		render() {
			if ( this.state.hasError ) {
				return el(
					Notice,
					{ status: 'error', isDismissible: false },
					__( 'Audio Providers UI encountered an error: ', 'mds-audio-providers' ) + this.state.message
				);
			}
			return this.props.children;
		}
	}

	function AudioProvidersApp() {
		const initial = boot.initial || {};
		const [ providers, setProviders ] = useState( Array.isArray( initial.providers ) ? initial.providers : [] );
		const [ credentials, setCredentials ] = useState( initial.credentials || {} );
		const [ cache, setCache ] = useState( initial.cache || { count: 0, total_size: '0 B', entries: [] } );
		const [ saving, setSaving ] = useState( false );
		const [ testing, setTesting ] = useState( '' );
		const [ clearing, setClearing ] = useState( false );
		const [ notices, setNotices ] = useState( [] );

		const [ scId, setScId ] = useState( '' );
		const [ scSecret, setScSecret ] = useState( '' );
		const [ spId, setSpId ] = useState( '' );
		const [ spSecret, setSpSecret ] = useState( '' );

		const pushNotice = useCallback( ( content ) => {
			const id = String( Date.now() + Math.random() );
			setNotices( ( prev ) => prev.concat( [ { id, content } ] ) );
			speak( content, 'polite' );
		}, [] );

		const dismissNotice = useCallback( ( id ) => {
			setNotices( ( prev ) => prev.filter( ( n ) => n.id !== id ) );
		}, [] );

		const moveProvider = ( index, delta ) => {
			const target = index + delta;
			if ( target < 0 || target >= providers.length ) {
				return;
			}
			const next = providers.slice();
			const [ item ] = next.splice( index, 1 );
			next.splice( target, 0, item );
			setProviders( next );
		};

		const toggleProvider = ( slug, enabled ) => {
			setProviders( ( prev ) =>
				prev.map( ( p ) => ( p.slug === slug ? Object.assign( {}, p, { enabled } ) : p ) )
			);
		};

		const saveAll = async () => {
			setSaving( true );
			try {
				const payload = {
					priority: providers.map( ( p ) => p.slug ),
					disabled: providers.filter( ( p ) => ! p.enabled ).map( ( p ) => p.slug ),
					credentials: {
						soundcloud: { client_id: scId, client_secret: scSecret },
						spotify: { client_id: spId, client_secret: spSecret },
					},
				};
				const res = await apiFetch( {
					path: restBase + '/settings',
					method: 'POST',
					data: payload,
				} );
				if ( Array.isArray( res.providers ) ) {
					setProviders( res.providers );
				}
				if ( res.credentials ) {
					setCredentials( res.credentials );
				}
				if ( res.cache ) {
					setCache( res.cache );
				}
				setScId( '' );
				setScSecret( '' );
				setSpId( '' );
				setSpSecret( '' );
				pushNotice( res.message || __( 'Settings saved.', 'mds-audio-providers' ) );
			} catch ( err ) {
				pushNotice( ( err && err.message ) || __( 'Failed to save settings.', 'mds-audio-providers' ) );
			} finally {
				setSaving( false );
			}
		};

		const testProvider = async ( slug ) => {
			setTesting( slug );
			try {
				const res = await apiFetch( {
					path: restBase + '/test-connection',
					method: 'POST',
					data: { provider: slug },
				} );
				pushNotice( res.message || ( res.ok ? __( 'Connection succeeded.', 'mds-audio-providers' ) : __( 'Connection failed.', 'mds-audio-providers' ) ) );
			} catch ( err ) {
				pushNotice( ( err && err.message ) || __( 'Connection test failed.', 'mds-audio-providers' ) );
			} finally {
				setTesting( '' );
			}
		};

		const clearCache = async () => {
			setClearing( true );
			try {
				const res = await apiFetch( {
					path: restBase + '/clear-cache',
					method: 'POST',
				} );
				if ( res.cache ) {
					setCache( res.cache );
				}
				pushNotice( res.message || __( 'Cache cleared.', 'mds-audio-providers' ) );
			} catch ( err ) {
				pushNotice( ( err && err.message ) || __( 'Failed to clear cache.', 'mds-audio-providers' ) );
			} finally {
				setClearing( false );
			}
		};

		const renderProvidersTab = () =>
			el(
				Fragment,
				null,
				! boot.hasCore &&
					el(
						Notice,
						{ status: 'warning', isDismissible: false },
						__( 'MDS Release Manager (1.2.0+) with the Unified Audio API is required.', 'mds-audio-providers' )
					),
				el(
					Card,
					{ style: { marginBottom: '16px' } },
					el( CardHeader, null, el( 'strong', null, __( 'Provider Priority & Fallback Order', 'mds-audio-providers' ) ) ),
					el(
						CardBody,
						null,
						el(
							'p',
							{ className: 'description', style: { marginTop: 0 } },
							__( 'Providers are tried top-to-bottom until one returns a playable preview. Apple Music, Deezer, and Spotify resolve automatically via each release UPC/EAN.', 'mds-audio-providers' )
						),
						providers.map( ( provider, idx ) =>
							el(
								'div',
								{
									key: provider.slug,
									style: {
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'space-between',
										gap: '12px',
										padding: '10px 12px',
										border: '1px solid #dcdcde',
										borderRadius: '6px',
										marginBottom: '8px',
										background: '#fff',
									},
								},
								el( ToggleControl, {
									label: provider.label + ' (' + provider.slug + ')',
									checked: !! provider.enabled,
									onChange: ( val ) => toggleProvider( provider.slug, val ),
									__nextHasNoMarginBottom: true,
								} ),
								el(
									'div',
									{ style: { display: 'flex', gap: '6px' } },
									el(
										Button,
										{
											variant: 'secondary',
											size: 'small',
											disabled: idx === 0,
											onClick: () => moveProvider( idx, -1 ),
											'aria-label': __( 'Move up', 'mds-audio-providers' ),
										},
										'↑'
									),
									el(
										Button,
										{
											variant: 'secondary',
											size: 'small',
											disabled: idx === providers.length - 1,
											onClick: () => moveProvider( idx, 1 ),
											'aria-label': __( 'Move down', 'mds-audio-providers' ),
										},
										'↓'
									)
								)
							)
						)
					)
				),
				el(
					Card,
					{ style: { marginBottom: '16px' } },
					el( CardHeader, null, el( 'strong', null, __( 'API Credentials (SoundCloud & Spotify)', 'mds-audio-providers' ) ) ),
					el(
						CardBody,
						null,
						el( 'h3', { style: { marginTop: 0 } }, __( 'SoundCloud (OAuth 2.1 Client Credentials)', 'mds-audio-providers' ) ),
						credentials.soundcloud && credentials.soundcloud.configured &&
							el(
								'p',
								{ className: 'description' },
								__( 'Configured: ', 'mds-audio-providers' ) + credentials.soundcloud.client_id_masked + ' (' + credentials.soundcloud.source + ')'
							),
						el( TextControl, {
							label: __( 'SoundCloud Client ID', 'mds-audio-providers' ),
							value: scId,
							placeholder: credentials.soundcloud && credentials.soundcloud.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
							onChange: setScId,
						} ),
						el( TextControl, {
							label: __( 'SoundCloud Client Secret', 'mds-audio-providers' ),
							type: 'password',
							value: scSecret,
							placeholder: credentials.soundcloud && credentials.soundcloud.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
							onChange: setScSecret,
						} ),
						el(
							Button,
							{
								variant: 'secondary',
								isBusy: testing === 'soundcloud',
								disabled: testing !== '',
								onClick: () => testProvider( 'soundcloud' ),
							},
							__( 'Test SoundCloud Connection', 'mds-audio-providers' )
						),
						el( 'hr', { style: { margin: '20px 0' } } ),
						el( 'h3', null, __( 'Spotify Web API (OAuth 2.0 Client Credentials)', 'mds-audio-providers' ) ),
						el(
							'p',
							{ className: 'description' },
							__( 'Spotify returns native 30s preview_url clips for Extended Quota Mode apps; standard apps fall through cleanly to Apple Music, Deezer, or Bandcamp.', 'mds-audio-providers' )
						),
						credentials.spotify && credentials.spotify.configured &&
							el(
								'p',
								{ className: 'description' },
								__( 'Configured: ', 'mds-audio-providers' ) + credentials.spotify.client_id_masked + ' (' + credentials.spotify.source + ')'
							),
						el( TextControl, {
							label: __( 'Spotify Client ID', 'mds-audio-providers' ),
							value: spId,
							placeholder: credentials.spotify && credentials.spotify.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
							onChange: setSpId,
						} ),
						el( TextControl, {
							label: __( 'Spotify Client Secret', 'mds-audio-providers' ),
							type: 'password',
							value: spSecret,
							placeholder: credentials.spotify && credentials.spotify.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
							onChange: setSpSecret,
						} ),
						el(
							Button,
							{
								variant: 'secondary',
								isBusy: testing === 'spotify',
								disabled: testing !== '',
								onClick: () => testProvider( 'spotify' ),
							},
							__( 'Test Spotify Connection', 'mds-audio-providers' )
						)
					)
				),
				el(
					Button,
					{
						variant: 'primary',
						isBusy: saving,
						disabled: saving,
						onClick: saveAll,
					},
					__( 'Save Audio Provider Settings', 'mds-audio-providers' )
				)
			);

		const renderCacheTab = () =>
			el(
				Card,
				null,
				el( CardHeader, null, el( 'strong', null, __( 'Provider Transient Cache', 'mds-audio-providers' ) ) ),
				el(
					CardBody,
					null,
					el(
						'p',
						null,
						__( 'Active cached provider lookups: ', 'mds-audio-providers' ) +
							String( cache.count || 0 ) +
							' (' +
							String( cache.total_size || '0 B' ) +
							')'
					),
					el(
						Button,
						{
							variant: 'secondary',
							isDestructive: true,
							isBusy: clearing,
							disabled: clearing,
							onClick: clearCache,
						},
						__( 'Clear Audio Provider Cache', 'mds-audio-providers' )
					)
				)
			);

		return el(
			'div',
			{ style: { maxWidth: '880px' } },
			el( 'h1', null, __( 'MDS Audio Providers', 'mds-audio-providers' ) ),
			el( TabPanel, {
				tabs: [
					{ name: 'providers', title: __( 'Providers & API Keys', 'mds-audio-providers' ) },
					{ name: 'cache', title: __( 'Cache & Diagnostics', 'mds-audio-providers' ) },
				],
				children: ( tab ) => ( tab.name === 'cache' ? renderCacheTab() : renderProvidersTab() ),
			} ),
			el( SnackbarList, { notices, onRemove: dismissNotice } )
		);
	}

	function mount() {
		const rootEl = document.getElementById( 'mds-audio-providers-admin-root' );
		if ( ! rootEl ) {
			return;
		}
		if ( wp.element.createRoot ) {
			wp.element.createRoot( rootEl ).render( el( AdminErrorBoundary, null, el( AudioProvidersApp ) ) );
		} else if ( wp.element.render ) {
			wp.element.render( el( AdminErrorBoundary, null, el( AudioProvidersApp ) ), rootEl );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount );
	} else {
		mount();
	}
} )( window.wp );
