/**
 * MDS Audio Providers — WordPress 7.x React Admin UI.
 *
 * Uses MDS Release Manager's shared design primitives (`window.mdsRmStylePrimitives`)
 * when mounted inside Releases → Settings, with clean standalone fallbacks.
 */
( function ( wp ) {
	'use strict';

	const { createElement: el, useState, useCallback, Component, Fragment } = wp.element;
	const {
		Button,
		Notice,
		PanelBody,
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

	const PROVIDER_ICONS = {
		local: 'dashicons dashicons-format-audio',
		apple_music: 'fab fa-apple',
		deezer: 'fas fa-music',
		bandcamp: 'fab fa-bandcamp',
		audius: 'fas fa-wave-square',
		soundcloud: 'fab fa-soundcloud',
		jamendo: 'fas fa-compact-disc',
		funkwhale: 'fas fa-broadcast-tower',
		spotify: 'fab fa-spotify',
		tidal: 'fas fa-water',
		mixcloud: 'fab fa-mixcloud',
		audiomack: 'fas fa-headphones',
		musicfetch: 'fas fa-satellite-dish',
	};

	const PROVIDER_DESCRIPTIONS = {
		local: __( 'Plays the Release Audio Preview URL (.mp3/.m4a) configured directly on each Release.', 'mds-audio-providers' ),
		apple_music: __( 'Resolves native 30-second .m4a previews automatically by Release UPC/EAN via the public iTunes Lookup API. Zero API keys required.', 'mds-audio-providers' ),
		deezer: __( 'Resolves native 30-second .mp3 previews automatically by Release UPC/EAN via Deezer’s public API. Zero API keys required.', 'mds-audio-providers' ),
		bandcamp: __( 'Resolves Bandcamp album tracks Just-in-Time from each release’s verified Bandcamp Album ID, with optional EmbeddedPlayer iframe fallback.', 'mds-audio-providers' ),
		audius: __( 'Resolves native 320kbps HTML5 audio streams from Audius track, album, or playlist URLs via the Open Audio Protocol. Zero API keys required.', 'mds-audio-providers' ),
		soundcloud: __( 'Resolves official 30-second previews (/preview) and streams for releases with a SoundCloud URL. Requires SoundCloud OAuth 2.1 credentials.', 'mds-audio-providers' ),
		jamendo: __( 'Resolves direct HTML5 .mp3 streams for Jamendo albums and tracks via Jamendo API v3.0. Requires a free Jamendo Client ID.', 'mds-audio-providers' ),
		funkwhale: __( 'Resolves native HTML5 audio streams from self-hosted or Fediverse Funkwhale pods. Public pods work zero-auth; add a Pod URL & optional Bearer token for private pods or connection testing.', 'mds-audio-providers' ),
		spotify: __( 'Looks up releases by UPC/EAN via Spotify Web API for native 30s preview_url, with optional official Spotify iframe embed fallback.', 'mds-audio-providers' ),
		tidal: __( 'Resolves TIDAL albums by UPC/EAN via TIDAL Open API v2 (or directly from a Release’s TIDAL URL) and renders the official TIDAL embed player.', 'mds-audio-providers' ),
		mixcloud: __( 'Renders the official Mixcloud embed widget for DJ sets, radio shows, and long-form mixes from a Release’s Mixcloud URL. Zero API keys required.', 'mds-audio-providers' ),
		audiomack: __( 'Renders the official Audiomack embed player for songs, albums, and playlists from a Release’s Audiomack URL. Zero API keys required.', 'mds-audio-providers' ),
		musicfetch: __( 'Optional commercial multi-DSP aggregator (BYO paid key). Queries Musicfetch by Release UPC/EAN across 30+ services for timed preview clips.', 'mds-audio-providers' ),
	};

	const CREDENTIAL_CONFIGS = {
		soundcloud: {
			idLabel: __( 'SoundCloud Client ID', 'mds-audio-providers' ),
			secretLabel: __( 'SoundCloud Client Secret', 'mds-audio-providers' ),
			hasId: true,
			hasSecret: true,
			testLabel: __( 'Test SoundCloud Connection', 'mds-audio-providers' ),
		},
		spotify: {
			idLabel: __( 'Spotify Client ID', 'mds-audio-providers' ),
			secretLabel: __( 'Spotify Client Secret', 'mds-audio-providers' ),
			hasId: true,
			hasSecret: true,
			testLabel: __( 'Test Spotify Connection', 'mds-audio-providers' ),
		},
		tidal: {
			idLabel: __( 'TIDAL Client ID', 'mds-audio-providers' ),
			secretLabel: __( 'TIDAL Client Secret', 'mds-audio-providers' ),
			hasId: true,
			hasSecret: true,
			testLabel: __( 'Test TIDAL Connection', 'mds-audio-providers' ),
		},
		jamendo: {
			idLabel: __( 'Jamendo Client ID', 'mds-audio-providers' ),
			secretLabel: '',
			hasId: true,
			hasSecret: false,
			testLabel: __( 'Test Jamendo Connection', 'mds-audio-providers' ),
		},
		funkwhale: {
			idLabel: __( 'Funkwhale Pod Base URL (optional)', 'mds-audio-providers' ),
			secretLabel: __( 'Funkwhale Bearer Token (optional, private pods)', 'mds-audio-providers' ),
			hasId: true,
			hasSecret: true,
			testLabel: __( 'Test Funkwhale Pod Connection', 'mds-audio-providers' ),
		},
		musicfetch: {
			idLabel: '',
			secretLabel: __( 'Musicfetch API Key (x-token)', 'mds-audio-providers' ),
			hasId: false,
			hasSecret: true,
			testLabel: __( 'Test Musicfetch Connection', 'mds-audio-providers' ),
		},
	};

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

	function FallbackSectionInfo( { title, summary, inheritance } ) {
		return el(
			'div',
			{ className: 'mds-section-info', role: 'note' },
			el( 'span', { className: 'dashicons dashicons-info-outline mds-section-info__icon', 'aria-hidden': true } ),
			el(
				'div',
				{ className: 'mds-section-info__body' },
				title && el( 'strong', { className: 'mds-section-info__title' }, title ),
				summary && el( 'p', { className: 'mds-section-info__summary' }, summary ),
				inheritance && el( 'p', { className: 'mds-section-info__inheritance' }, inheritance )
			)
		);
	}

	function AudioProvidersApp() {
		const primitives = window.mdsRmStylePrimitives || {};
		const SectionInfo = primitives.SectionInfo || FallbackSectionInfo;
		const RepeaterCard = primitives.RepeaterCard || null;

		const initial = boot.initial || {};
		const isEmbeddedTab = Boolean( document.querySelector( '.mds-settings-wrap #mds-audio-providers-admin-root' ) );
		const [ providers, setProviders ] = useState( Array.isArray( initial.providers ) ? initial.providers : [] );
		const [ credentials, setCredentials ] = useState( initial.credentials || {} );
		const [ providerOptions, setProviderOptions ] = useState( initial.provider_options || {} );
		const [ cache, setCache ] = useState( initial.cache || { count: 0, total_size: '0 B', entries: [] } );
		const [ saving, setSaving ] = useState( false );
		const [ testing, setTesting ] = useState( '' );
		const [ clearing, setClearing ] = useState( false );
		const [ notices, setNotices ] = useState( [] );

		const [ credInputs, setCredInputs ] = useState( {} );

		const updateCredInput = ( slug, field, value ) => {
			setCredInputs( ( prev ) => {
				const entry = Object.assign( { client_id: '', client_secret: '' }, prev[ slug ] || {} );
				entry[ field ] = value;
				return Object.assign( {}, prev, { [ slug ]: entry } );
			} );
		};

		const toggleProviderOption = ( slug, key, value ) => {
			setProviderOptions( ( prev ) => {
				const current = Object.assign( {}, prev[ slug ] || {} );
				current[ key ] = !! value;
				return Object.assign( {}, prev, { [ slug ]: current } );
			} );
		};

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
					credentials: credInputs,
					provider_options: providerOptions,
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
				if ( res.provider_options ) {
					setProviderOptions( res.provider_options );
				}
				if ( res.cache ) {
					setCache( res.cache );
				}
				setCredInputs( {} );
				pushNotice( res.message || __( 'Audio provider settings saved.', 'mds-audio-providers' ) );
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

		const providerBadge = ( provider ) => {
			if ( ! provider.enabled ) {
				return __( 'Disabled', 'mds-audio-providers' );
			}
			if ( provider.slug === 'soundcloud' || provider.slug === 'jamendo' || provider.slug === 'musicfetch' ) {
				const cred = credentials[ provider.slug ];
				return cred && cred.configured
					? __( 'Configured', 'mds-audio-providers' )
					: ( provider.slug === 'musicfetch' ? __( 'Optional Paid Key', 'mds-audio-providers' ) : __( 'Needs API Key', 'mds-audio-providers' ) );
			}
			if ( provider.slug === 'spotify' ) {
				const cred = credentials.spotify;
				const embedEnabled = ! providerOptions.spotify || providerOptions.spotify.embed_fallback !== false;
				if ( cred && cred.configured ) {
					return __( 'Configured', 'mds-audio-providers' );
				}
				return embedEnabled
					? __( 'Embed Only', 'mds-audio-providers' )
					: __( 'Needs API Key', 'mds-audio-providers' );
			}
			if ( provider.slug === 'tidal' ) {
				const cred = credentials.tidal;
				return cred && cred.configured
					? __( 'UPC + Embed', 'mds-audio-providers' )
					: __( 'URL Embed', 'mds-audio-providers' );
			}
			if ( provider.slug === 'funkwhale' ) {
				const cred = credentials.funkwhale;
				return cred && cred.configured
					? __( 'Pod Configured', 'mds-audio-providers' )
					: __( 'Self-Hosted', 'mds-audio-providers' );
			}
			if ( provider.slug === 'mixcloud' || provider.slug === 'audiomack' ) {
				return __( 'Zero-Auth Embed', 'mds-audio-providers' );
			}
			return __( 'Zero-Auth', 'mds-audio-providers' );
		};

		const renderProviderDrawer = ( provider ) => {
			const slug = provider.slug;
			const desc = PROVIDER_DESCRIPTIONS[ slug ] || '';
			const isSp = slug === 'spotify';
			const hasEmbedToggle = slug === 'spotify' || slug === 'bandcamp';
			const embedChecked = ! providerOptions[ slug ] || providerOptions[ slug ].embed_fallback !== false;
			const cred = credentials[ slug ];
			const credConfig = CREDENTIAL_CONFIGS[ slug ] || null;
			const inputState = credInputs[ slug ] || { client_id: '', client_secret: '' };

			return el(
				Fragment,
				null,
				desc && el( 'p', { className: 'mds-style-field__help', style: { marginTop: 0 } }, desc ),
				hasEmbedToggle &&
					el(
						'div',
						{ style: { marginTop: '12px' } },
						el( ToggleControl, {
							label: __( 'Allow iframe embed fallback', 'mds-audio-providers' ),
							help: isSp
								? __( 'When enabled, renders the official Spotify embed player if native preview_url tracks are unavailable (or when a Spotify URL is present without API keys).', 'mds-audio-providers' )
								: __( 'When enabled, falls back to Bandcamp’s official EmbeddedPlayer iframe if direct track streams cannot be resolved.', 'mds-audio-providers' ),
							checked: embedChecked,
							onChange: ( val ) => toggleProviderOption( slug, 'embed_fallback', val ),
							__nextHasNoMarginBottom: true,
						} )
					),
				credConfig &&
					el(
						'div',
						{ className: 'mds-style-grid-2col', style: { marginTop: '12px' } },
						cred && cred.configured &&
							el(
								'p',
								{ className: 'description', style: { gridColumn: '1 / -1', margin: 0 } },
								__( 'Active Key: ', 'mds-audio-providers' ) + cred.client_id_masked + ' (' + cred.source + ')'
							),
						credConfig.hasId &&
							el( TextControl, {
								label: credConfig.idLabel,
								value: inputState.client_id,
								placeholder: cred && cred.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
								onChange: ( val ) => updateCredInput( slug, 'client_id', val ),
								__next40pxDefaultSize: true,
								__nextHasNoMarginBottom: true,
							} ),
						credConfig.hasSecret &&
							el( TextControl, {
								label: credConfig.secretLabel,
								type: 'password',
								value: inputState.client_secret,
								placeholder: cred && cred.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
								onChange: ( val ) => updateCredInput( slug, 'client_secret', val ),
								__next40pxDefaultSize: true,
								__nextHasNoMarginBottom: true,
							} ),
						el(
							'div',
							{ style: { gridColumn: '1 / -1' } },
							el(
								Button,
								{
									variant: 'secondary',
									isBusy: testing === slug,
									disabled: testing !== '',
									onClick: () => testProvider( slug ),
								},
								credConfig.testLabel
							)
						)
					)
			);
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
					PanelBody,
					{ title: __( 'Provider Priority & API Credentials', 'mds-audio-providers' ), initialOpen: true },
					el( SectionInfo, {
						title: __( 'About Unified Audio Providers', 'mds-audio-providers' ),
						summary: __( 'Providers are tried top-to-bottom until one returns a playable preview. Apple Music, Deezer, and Spotify resolve automatically by Release UPC/EAN. Click Customize on SoundCloud or Spotify to configure API keys and test connections.', 'mds-audio-providers' ),
						inheritance: __( 'Powers the Unified Release Player block, [release-player] shortcode, and Release Links audio slot.', 'mds-audio-providers' ),
					} ),
					el(
						'div',
						{ className: 'mds-repeater-list' },
						providers.map( ( provider, idx ) => {
							if ( RepeaterCard ) {
								return el(
									RepeaterCard,
									{
										key: provider.slug,
										icon: PROVIDER_ICONS[ provider.slug ] || 'dashicons dashicons-format-audio',
										title: provider.label,
										badge: providerBadge( provider ),
										enabled: !! provider.enabled,
										onToggleEnabled: ( val ) => toggleProvider( provider.slug, val ),
										canMoveUp: idx > 0,
										canMoveDown: idx < providers.length - 1,
										onMoveUp: () => moveProvider( idx, -1 ),
										onMoveDown: () => moveProvider( idx, 1 ),
									},
									renderProviderDrawer( provider )
								);
							}

							return el(
								PanelBody,
								{ key: provider.slug, title: provider.label + ' — ' + providerBadge( provider ), initialOpen: false },
								el( ToggleControl, {
									label: __( 'Enable provider', 'mds-audio-providers' ),
									checked: !! provider.enabled,
									onChange: ( val ) => toggleProvider( provider.slug, val ),
									__nextHasNoMarginBottom: true,
								} ),
								renderProviderDrawer( provider )
							);
						} )
					),
					el(
						'div',
						{ className: 'mds-admin-save-actions' },
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
					)
				)
			);

		const renderCacheTab = () =>
			el(
				PanelBody,
				{ title: __( 'Provider Transient Cache', 'mds-audio-providers' ), initialOpen: true },
				el( SectionInfo, {
					title: __( 'About Audio Provider Caching', 'mds-audio-providers' ),
					summary: __( 'Provider manifests are cached selectively in WordPress transients respecting each service’s stream TTL (15–30 minutes for signed streams; 12 hours for UPC lookups). Clearing this cache also notifies MDS Release Manager’s Cache Manager.', 'mds-audio-providers' ),
				} ),
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
			);

		return el(
			'div',
			{ className: 'mds-ap-workspace' },
			! isEmbeddedTab && el( 'h1', null, __( 'MDS Audio Providers', 'mds-audio-providers' ) ),
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
