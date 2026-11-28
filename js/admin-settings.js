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

	const TIER_RANK = { free: 10, pro: 20, label: 30, agency: 40 };

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
		apple_music: __( 'Free Tier · Cloud Gateway — Resolves native 30-second .m4a previews automatically by Release UPC/EAN. Zero API keys required.', 'mds-audio-providers' ),
		deezer: __( 'Free Tier · Cloud Gateway — Resolves native 30-second .mp3 previews automatically by Release UPC/EAN. Zero API keys required.', 'mds-audio-providers' ),
		bandcamp: __( 'Free Tier · Cloud Gateway — Resolves Bandcamp album tracks Just-in-Time from each release’s verified Bandcamp Album ID, with optional EmbeddedPlayer iframe fallback.', 'mds-audio-providers' ),
		audius: __( 'Free Tier · Cloud Gateway — Resolves native 320kbps HTML5 audio streams from Audius track, album, or playlist URLs. Zero API keys required.', 'mds-audio-providers' ),
		mixcloud: __( 'Free Tier · Cloud Gateway — Renders the official Mixcloud embed widget for DJ sets and radio shows. Zero API keys required.', 'mds-audio-providers' ),
		audiomack: __( 'Free Tier · Cloud Gateway — Renders the official Audiomack embed player for songs, albums, and playlists. Zero API keys required.', 'mds-audio-providers' ),
		soundcloud: __( 'Pro Tier · Cloud Gateway — Resolves official 30-second previews and streams for SoundCloud URLs using centrally managed MDS credentials (zero setup).', 'mds-audio-providers' ),
		jamendo: __( 'Pro Tier · Cloud Gateway — Resolves direct HTML5 .mp3 streams for Jamendo albums and tracks using centrally managed MDS credentials (zero setup).', 'mds-audio-providers' ),
		funkwhale: __( 'Pro Tier · Local Pod — Resolves native HTML5 audio streams directly from your self-hosted or private Funkwhale pod. Configure an optional Pod URL & Bearer token below.', 'mds-audio-providers' ),
		spotify: __( 'Pro Tier · Cloud Gateway — Resolves releases by UPC/EAN via Spotify Web API using centrally managed MDS credentials, with optional official Spotify iframe embed fallback.', 'mds-audio-providers' ),
		tidal: __( 'Pro Tier · Cloud Gateway — Resolves TIDAL albums by UPC/EAN or TIDAL URL using centrally managed MDS credentials and renders the official TIDAL embed player.', 'mds-audio-providers' ),
		musicfetch: __( 'Pro Tier · Cloud Gateway — Queries Musicfetch by Release UPC/EAN across 30+ streaming services using MDS’s centrally managed commercial subscription.', 'mds-audio-providers' ),
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
		const [ providerTiers ] = useState( initial.provider_tiers || {} );
		const [ license, setLicense ] = useState( initial.license || { activated: false, tier: '', tier_label: '', key_masked: '', api_base_url: '' } );
		const [ licenseInput, setLicenseInput ] = useState( '' );
		const [ licenseBusy, setLicenseBusy ] = useState( false );
		const [ licenseError, setLicenseError ] = useState( '' );
		const [ credentials, setCredentials ] = useState( initial.credentials || {} );
		const [ providerOptions, setProviderOptions ] = useState( initial.provider_options || {} );
		const [ cache, setCache ] = useState( initial.cache || { count: 0, total_size: '0 B', entries: [] } );
		const [ saving, setSaving ] = useState( false );
		const [ testing, setTesting ] = useState( '' );
		const [ clearing, setClearing ] = useState( false );
		const [ notices, setNotices ] = useState( [] );
		const [ fwInput, setFwInput ] = useState( { client_id: '', client_secret: '' } );

		const hasTier = ( minTier ) => {
			if ( ! minTier ) {
				return true;
			}
			if ( ! license || ! license.activated ) {
				return false;
			}
			const actual = TIER_RANK[ String( license.tier || '' ).toLowerCase() ] || 0;
			const req = TIER_RANK[ String( minTier ).toLowerCase() ] || 999;
			return actual >= req;
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

		const syncStateFromResponse = ( res ) => {
			if ( Array.isArray( res.providers ) ) {
				setProviders( res.providers );
			}
			if ( res.license ) {
				setLicense( res.license );
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
		};

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

		const activateLicense = async () => {
			if ( ! licenseInput.trim() ) {
				setLicenseError( __( 'Enter a license key first.', 'mds-audio-providers' ) );
				return;
			}
			setLicenseBusy( true );
			setLicenseError( '' );
			try {
				const res = await apiFetch( {
					path: restBase + '/license/activate',
					method: 'POST',
					data: { license_key: licenseInput.trim() },
				} );
				syncStateFromResponse( res );
				setLicenseInput( '' );
				pushNotice( res.message || __( 'License activated.', 'mds-audio-providers' ) );
			} catch ( err ) {
				const msg = ( err && err.message ) || __( 'License activation failed.', 'mds-audio-providers' );
				setLicenseError( msg );
				pushNotice( msg );
			} finally {
				setLicenseBusy( false );
			}
		};

		const deactivateLicense = async () => {
			setLicenseBusy( true );
			try {
				const res = await apiFetch( {
					path: restBase + '/license/deactivate',
					method: 'POST',
				} );
				syncStateFromResponse( res );
				pushNotice( res.message || __( 'License deactivated.', 'mds-audio-providers' ) );
			} catch ( err ) {
				pushNotice( ( err && err.message ) || __( 'Failed to deactivate license.', 'mds-audio-providers' ) );
			} finally {
				setLicenseBusy( false );
			}
		};

		const saveAll = async () => {
			setSaving( true );
			try {
				const payload = {
					priority: providers.map( ( p ) => p.slug ),
					disabled: providers.filter( ( p ) => ! p.enabled ).map( ( p ) => p.slug ),
					credentials: fwInput.client_id || fwInput.client_secret ? { funkwhale: fwInput } : {},
					provider_options: providerOptions,
				};
				const res = await apiFetch( {
					path: restBase + '/settings',
					method: 'POST',
					data: payload,
				} );
				syncStateFromResponse( res );
				setFwInput( { client_id: '', client_secret: '' } );
				pushNotice( res.message || __( 'Audio provider settings saved.', 'mds-audio-providers' ) );
			} catch ( err ) {
				pushNotice( ( err && err.message ) || __( 'Failed to save settings.', 'mds-audio-providers' ) );
			} finally {
				setSaving( false );
			}
		};

		const testFunkwhale = async () => {
			setTesting( 'funkwhale' );
			try {
				const res = await apiFetch( {
					path: restBase + '/test-connection',
					method: 'POST',
					data: { provider: 'funkwhale' },
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
			if ( provider.slug === 'local' ) {
				return __( 'Core · Local', 'mds-audio-providers' );
			}
			const minTier = providerTiers[ provider.slug ] || 'free';
			if ( ! license.activated ) {
				return __( 'Needs License', 'mds-audio-providers' );
			}
			if ( ! hasTier( minTier ) ) {
				return __( 'Requires Pro', 'mds-audio-providers' );
			}
			if ( provider.slug === 'funkwhale' ) {
				const cred = credentials.funkwhale;
				return cred && cred.configured
					? __( 'Pro · Pod Configured', 'mds-audio-providers' )
					: __( 'Pro · Local Pod', 'mds-audio-providers' );
			}
			return minTier === 'pro'
				? __( 'Pro · Gateway', 'mds-audio-providers' )
				: __( 'Free · Gateway', 'mds-audio-providers' );
		};

		const renderLicensePanel = () =>
			el(
				PanelBody,
				{
					title: __( 'MDS Cloud Gateway License', 'mds-audio-providers' ),
					initialOpen: ! license.activated,
				},
				el( SectionInfo, {
					title: license.activated
						? __( 'Connected to MDS Cloud Gateway', 'mds-audio-providers' ) + ' — ' + ( license.tier_label || license.tier )
						: __( 'Activate Your Free or Pro MDS License', 'mds-audio-providers' ),
					summary: license.activated
						? __( 'Active Key: ', 'mds-audio-providers' ) + license.key_masked + ' · ' + __( 'Endpoint: ', 'mds-audio-providers' ) + license.api_base_url
						: __( 'Free-tier keys unlock Apple Music, Deezer, Bandcamp, Audius, Mixcloud, and Audiomack. Pro-tier keys additionally unlock Spotify, SoundCloud, TIDAL, Jamendo, Musicfetch, and private Funkwhale pods with zero API key setup.', 'mds-audio-providers' ),
				} ),
				licenseError &&
					el(
						'div',
						{ style: { marginTop: '12px' } },
						el(
							Notice,
							{
								status: 'error',
								isDismissible: true,
								onRemove: () => setLicenseError( '' ),
							},
							licenseError
						)
					),
				license.activated
					? el(
						'div',
						{ style: { marginTop: '12px' } },
						el(
							Button,
							{
								variant: 'secondary',
								isDestructive: true,
								isBusy: licenseBusy,
								disabled: licenseBusy,
								onClick: deactivateLicense,
							},
							__( 'Deactivate License', 'mds-audio-providers' )
						)
					)
					: el(
						'div',
						{ className: 'mds-style-grid-2col', style: { marginTop: '12px', alignItems: 'end' } },
						el( TextControl, {
							label: __( 'MDS License Key', 'mds-audio-providers' ),
							value: licenseInput,
							placeholder: 'MDS-FREE-XXXX-XXXX-XXXX-XXXX or MDS-PRO-...',
							onChange: setLicenseInput,
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} ),
						el(
							'div',
							null,
							el(
								Button,
								{
									variant: 'primary',
									isBusy: licenseBusy,
									disabled: licenseBusy,
									onClick: activateLicense,
								},
								__( 'Activate License', 'mds-audio-providers' )
							)
						)
					)
			);

		const renderProviderDrawer = ( provider ) => {
			const slug = provider.slug;
			const desc = PROVIDER_DESCRIPTIONS[ slug ] || '';
			const isSp = slug === 'spotify';
			const hasEmbedToggle = slug === 'spotify' || slug === 'bandcamp';
			const embedChecked = ! providerOptions[ slug ] || providerOptions[ slug ].embed_fallback !== false;
			const isFunkwhale = slug === 'funkwhale';
			const fwCred = credentials.funkwhale || {};

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
								? __( 'When enabled, renders the official Spotify embed player if native preview_url tracks are unavailable.', 'mds-audio-providers' )
								: __( 'When enabled, falls back to Bandcamp’s official EmbeddedPlayer iframe if direct track streams cannot be resolved.', 'mds-audio-providers' ),
							checked: embedChecked,
							onChange: ( val ) => toggleProviderOption( slug, 'embed_fallback', val ),
							__nextHasNoMarginBottom: true,
						} )
					),
				isFunkwhale &&
					el(
						'div',
						{ className: 'mds-style-grid-2col', style: { marginTop: '12px' } },
						fwCred.configured &&
							el(
								'p',
								{ className: 'description', style: { gridColumn: '1 / -1', margin: 0 } },
								__( 'Active Pod: ', 'mds-audio-providers' ) + fwCred.client_id_masked + ' (' + fwCred.source + ')'
							),
						el( TextControl, {
							label: __( 'Funkwhale Pod Base URL (optional)', 'mds-audio-providers' ),
							value: fwInput.client_id,
							placeholder: fwCred.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : 'https://open.audio',
							onChange: ( val ) => setFwInput( ( prev ) => Object.assign( {}, prev, { client_id: val } ) ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} ),
						el( TextControl, {
							label: __( 'Funkwhale Bearer Token (optional, private pods)', 'mds-audio-providers' ),
							type: 'password',
							value: fwInput.client_secret,
							placeholder: fwCred.configured ? __( 'Leave blank to keep existing', 'mds-audio-providers' ) : '',
							onChange: ( val ) => setFwInput( ( prev ) => Object.assign( {}, prev, { client_secret: val } ) ),
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
									isBusy: testing === 'funkwhale',
									disabled: testing !== '',
									onClick: testFunkwhale,
								},
								__( 'Test Funkwhale Pod Connection', 'mds-audio-providers' )
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
				renderLicensePanel(),
				el(
					PanelBody,
					{ title: __( 'Provider Priority & Tier Status', 'mds-audio-providers' ), initialOpen: true },
					el( SectionInfo, {
						title: __( 'About Unified Audio Providers', 'mds-audio-providers' ),
						summary: __( 'Providers are tried top-to-bottom until one returns a playable preview. All cloud providers are resolved centrally via MDS Cloud Gateway with zero customer API keys required.', 'mds-audio-providers' ),
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
					summary: __( 'Provider manifests are cached locally in WordPress transients respecting each service’s stream TTL (15–30 minutes for signed streams; 12 hours for UPC lookups) and also cached across sites on MDS Cloud Gateway.', 'mds-audio-providers' ),
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
					{ name: 'providers', title: __( 'License & Providers', 'mds-audio-providers' ) },
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
