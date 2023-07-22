import { registerCriteria } from '../../criteria/utils';
import { getBestPrioritySegment, shouldPromptBeDisplayed, periods } from './index.js';

// Mock some test criteria.
const criteria = {
	simple: {},
	list__in: {
		matchingFunction: 'list__in',
	},
	range: {
		matchingFunction: 'range',
	},
};

// Mock some test segments.
const segments = {
	segment1: {
		criteria: [
			{
				criteria_id: 'simple',
				value: 'simple-non-match',
			},
		],
		priority: 0,
	},
	segment2: {
		criteria: [
			{
				criteria_id: 'simple',
				value: 'simple-match',
			},
		],
		priority: 1,
	},
	segment3: {
		criteria: [
			{
				criteria_id: 'simple',
				value: 'simple-match',
			},
		],
		priority: 1,
	},
	segment4: {
		criteria: [
			{
				criteria_id: 'list__in',
				value: [ 'list-value', 'list-value-2' ],
			},
		],
		priority: 2,
	},
};

// Mock the RAS data library object.
const now = Date.now();
window.newspackReaderActivation = {
	store: {
		set( matchingAttribute, value ) {
			if ( ! this.values ) {
				this.values = {};
			}
			this.values[ matchingAttribute ] = value;
		},
		get( matchingAttribute ) {
			if ( ! this.values ) {
				this.values = {};
			}
			return this.values[ matchingAttribute ];
		},
		clear() {
			this.values = {};
		},
	},
	getActivities( action = null ) {
		const testActivities = [
			{
				action: 'article_view',
				timestamp: now - 60 * 60, // 1 hour ago.
			},
			{
				action: 'article_view',
				timestamp: now - periods.week, // 1 week ago.
			},
			{
				action: 'article_view',
				timestamp: now - periods.week * 3, // 3 weeks ago.
			},
			{
				action: 'prompt_seen',
				data: { prompt_id: 1 },
				timestamp: now - 60 * 60,
			},
		];

		if ( ! action ) {
			return testActivities;
		}

		return testActivities.filter( activity => activity.action === action );
	},
	on() {},
};

const ras = window.newspackReaderActivation;

const createPrompt = ( assignedSegments = [], frequency = '0,0,0,month' ) => {
	const prompt = document.createElement( 'div' );
	prompt.setAttribute( 'id', 'id_1' );
	prompt.setAttribute( 'data-segments', assignedSegments.join( ',' ) );
	prompt.setAttribute( 'data-frequency', frequency );
	return prompt;
};

describe( 'segmentation API', () => {
	beforeEach( () => {
		window.newspackPopupsCriteria = { criteria: {} };
		for ( const criteriaId in criteria ) {
			registerCriteria( criteriaId, criteria[ criteriaId ] );
		}
		ras.store.clear();
		ras.store.set( 'pageviews', {
			day: {
				count: 1,
				start: now,
			},
			week: {
				count: 1,
				start: now,
			},
			month: {
				count: 1,
				start: now,
			},
		} );
	} );

	it( 'should return null if the reader matches no segment', () => {
		// Set an initial value.
		ras.store.set( 'simple', 'initial-value' );
		expect( getBestPrioritySegment( segments ) ).toEqual( null );
	} );

	it( 'should return the segment ID of the matching segment with the highest priority', () => {
		ras.store.set( 'simple', 'simple-match' );
		expect( getBestPrioritySegment( segments ) ).toEqual( 'segment2' );
	} );

	it( 'should return false if the reader doesn’t match the prompt’s segments', () => {
		const prompt = createPrompt( [ 'segment4' ] );
		expect(
			shouldPromptBeDisplayed( prompt, getBestPrioritySegment( segments ), ras )
		).toBeFalsy();
	} );

	it( 'should return true if the reader matches the prompt’s segments', () => {
		const prompt = createPrompt( [ 'segment4' ] );
		ras.store.set( 'list__in', 'list-value' );
		expect(
			shouldPromptBeDisplayed( prompt, getBestPrioritySegment( segments ), ras )
		).toBeTruthy();
	} );

	it( 'should return true if the prompt has no assigned segments', () => {
		const prompt = createPrompt();
		expect( shouldPromptBeDisplayed( prompt, null, ras ) ).toBeTruthy();
	} );

	it( 'should return false if the reader hasn’t viewed enough articles', () => {
		const prompt = createPrompt( [], '2,0,0,month' );
		expect(
			shouldPromptBeDisplayed( prompt, getBestPrioritySegment( segments ), ras )
		).toBeFalsy();
	} );

	it( 'should return false if the reader has already viewed the prompt the max number of times', () => {
		const prompt = createPrompt( [], '0,0,1,month' );
		expect(
			shouldPromptBeDisplayed( prompt, getBestPrioritySegment( segments ), ras )
		).toBeFalsy();
	} );
} );
