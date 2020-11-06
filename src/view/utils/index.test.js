import { getCookieValueFromLinker } from '.';

describe( 'amp-analytics linker handling', () => {
	const url =
		'https://example.com/lorem/?ref_newspack_cid=1*ab3otu*cid*T2ptZDdJNU9EYndELWV0TlM1N0FwSGVwNHE3S240VkVVU0o3YlNsaFc2YVVPRXJhSWFhaVlsOTgtQXNsc21Eeg..';
	const ampAnalyticsConfig = {
		linkers: { enabled: true, ref_newspack_cid: {} },
		cookies: { enabled: true, 'newspack-cid': {} },
	};

	it( 'read linker param from URL and parse CID cookie value', () => {
		expect( getCookieValueFromLinker( ampAnalyticsConfig, url, '' ) ).toEqual( {
			cookieValue: 'newspack-cid=Ojmd7I5ODbwD-etNS57ApHep4q7Kn4VEUSJ7bSlhW6aUOEraIaaiYl98-AslsmDz',
			cleanURL: 'https://example.com/lorem/?',
		} );
	} );
	it( 'does not throw if malformed linker param is received', () => {
		expect(
			getCookieValueFromLinker(
				ampAnalyticsConfig,
				'https://example.com/lorem/?ref_newspack_cid=1*ab3otu*cid*,,,',
				''
			)
		).toEqual( { cookieValue: undefined, cleanURL: undefined } );
	} );
	it( 'return undefined if there is no linker param in the URL', () => {
		expect( getCookieValueFromLinker( ampAnalyticsConfig, 'example.com', '' ) ).toEqual( {
			cookieValue: undefined,
			cleanURL: undefined,
		} );
	} );
	it( 'return undefined if cookie is already set', () => {
		expect( getCookieValueFromLinker( ampAnalyticsConfig, url, 'newspack-cid=amp-123' ) ).toEqual( {
			cookieValue: undefined,
			cleanURL: undefined,
		} );
	} );
} );
