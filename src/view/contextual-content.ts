/**
 * Internal dependencies
 */
import { getBestPrioritySegment } from './utils/segments';

/**
 * Window extensions for reader activation and popup view data.
 */
declare global {
	interface Window {
		newspackReaderActivation?: {
			getActivities: () => Activity[];
		};
		newspack_popups_view?: {
			segments?: Record<string, any>;
		};
	}
}

/**
 * Context data for processing prompts.
 */
interface ContextualContentContext {
	segment: string | null;
	currentPostId: number | null;
}

/**
 * API request payload for contextual content.
 */
interface ContextualContentRequest {
	prompt_id: number;
	segment?: string | null;
	current_post_id?: number | null;
	content: string;
}

/**
 * API response for contextual content.
 */
interface ContextualContentResponse {
	content: string;
}

/* eslint-disable no-console */
/**
 * Debug logging function with suppressed console warnings.
 *
 * @param message The message to log.
 * @param data    Optional data to log.
 */
const debugLog = (message: string, data?: any): void => {
	if (data !== undefined) {
		console.log(message, data);
	} else {
		console.log(message);
	}
};

/**
 * Debug warning function with suppressed console warnings.
 *
 * @param message The message to warn.
 * @param data    Optional data to warn.
 */
const debugWarn = (message: string, data?: any): void => {
	if (data !== undefined) {
		console.warn(message, data);
	} else {
		console.warn(message);
	}
};

/**
 * Debug error function with suppressed console warnings.
 *
 * @param message The message to error.
 * @param data    Optional data to error.
 */
const debugError = (message: string, data?: any): void => {
	if (data !== undefined) {
		console.error(message, data);
	} else {
		console.error(message);
	}
};
/* eslint-enable no-console */

/**
 * Handle contextual content for prompts if the feature is enabled.
 *
 * @param prompts All prompts on the page.
 */
export const handleContextualContent = async (
	prompts: NodeListOf<Element>
): Promise<void> => {
	debugLog(
		'[Contextual Content] Starting contextual content processing...'
	);

	// Check if the feature is enabled via URL parameter.
	const urlParams = new URLSearchParams(window.location.search);
	if (!urlParams.has('newspack-contextual-prompt-content')) {
		debugLog(
			'[Contextual Content] Feature not enabled - missing URL parameter'
		);
		return;
	}

	debugLog('[Contextual Content] Feature enabled via URL parameter');

	// Get segments and reader's best matching segment.
	const segments = window.newspack_popups_view?.segments || {};
	let matchingSegment = null;
	
	// Only try to get matching segment if reader activation is available.
	// getBestPrioritySegment requires reader activation for segment criteria matching.
	if (window.newspackReaderActivation) {
		try {
			matchingSegment = getBestPrioritySegment(segments);
		} catch (error) {
			debugWarn('[Contextual Content] Error getting matching segment:', error);
			matchingSegment = null;
		}
	} else {
		debugLog('[Contextual Content] Reader activation not available, using null segment');
	}

	debugLog(
		'[Contextual Content] Available segments:',
		Object.keys(segments)
	);
	debugLog('[Contextual Content] Best matching segment:', matchingSegment);

	// Get current post ID if available.
	const currentPostId = document
		.querySelector('body')
		?.classList?.toString()
		.match(/postid-(\d+)/);
	const postId = currentPostId ? parseInt(currentPostId[1], 10) : null;

	debugLog('[Contextual Content] Current post ID:', postId);
	debugLog(
		'[Contextual Content] Found',
		prompts.length,
		'prompts to process'
	);

	// Process each prompt.
	for (const prompt of prompts) {
		await processPromptContextualContent(prompt as HTMLElement, {
			segment: matchingSegment,
			currentPostId: postId,
		});
	}

	debugLog('[Contextual Content] Finished processing all prompts');
};

/**
 * Process a single prompt for contextual content.
 *
 * @param prompt  The prompt element.
 * @param context The context data.
 */
const processPromptContextualContent = async (
	prompt: HTMLElement,
	context: ContextualContentContext
): Promise<void> => {
	const promptId = extractPromptId(prompt);
	if (!promptId) {
		debugWarn(
			'[Contextual Content] Could not extract prompt ID from element:',
			prompt
		);
		return;
	}

	debugLog(`[Contextual Content] Processing prompt ID: ${promptId}`);

	// Get the original content.
	// For most prompts, the content is directly in the container element
	let contentElement = prompt.querySelector(
		'.newspack-popup-content'
	) as HTMLElement;
	
	// If no specific content element found, use the prompt container itself
	if (!contentElement) {
		contentElement = prompt;
		debugLog(
			`[Contextual Content] Using prompt container as content element for prompt ${promptId}`
		);
	}

	const originalContent = contentElement.innerHTML;
	debugLog(
		`[Contextual Content] Original content length for prompt ${promptId}:`,
		originalContent.length
	);

	// Prepare request payload (no activities needed - handled server-side).
	const requestPayload: ContextualContentRequest = {
		prompt_id: promptId,
		segment: context.segment,
		current_post_id: context.currentPostId,
		content: originalContent,
	};

	debugLog(
		`[Contextual Content] API request payload for prompt ${promptId}:`,
		{
			prompt_id: promptId,
			segment: context.segment,
			current_post_id: context.currentPostId,
			content_length: originalContent.length,
		}
	);

	try {
		// Call the REST API endpoint.
		debugLog(
			`[Contextual Content] Making API request for prompt ${promptId}...`
		);
		const response = await fetch(
			'/wp-json/newspack-popups/v1/contextual-content',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(requestPayload),
			}
		);

		debugLog(
			`[Contextual Content] API response status for prompt ${promptId}:`,
			response.status
		);

		if (!response.ok) {
			const errorText = await response.text();
			debugWarn(
				`[Contextual Content] Failed to get contextual content for prompt ${promptId}`,
				'Status:',
				response.status,
				'Error:',
				errorText
			);
			return;
		}

		const data: ContextualContentResponse = await response.json();
		debugLog(
			`[Contextual Content] Received response for prompt ${promptId}:`,
			{
				content_length: data.content?.length || 0,
				content_changed: data.content !== originalContent,
			}
		);

		if (data.content && data.content !== originalContent) {
			debugLog(
				`[Contextual Content] Updating content for prompt ${promptId}`
			);

			// Log the content change for debugging.
			debugLog(
				`[Contextual Content] Content change for prompt ${promptId}:`,
				{
					original: originalContent.substring(0, 100) + '...',
					personalized: data.content.substring(0, 100) + '...',
				}
			);

			// Update the prompt content.
			contentElement.innerHTML = data.content;

			// Add a subtle indicator that the content has been personalized.
			if (!prompt.classList.contains('newspack-contextual-content')) {
				prompt.classList.add('newspack-contextual-content');
				debugLog(
					`[Contextual Content] Added contextual content class to prompt ${promptId}`
				);
			}

			debugLog(
				`[Contextual Content] Successfully personalized prompt ${promptId}`
			);
		} else {
			debugLog(
				`[Contextual Content] No content change needed for prompt ${promptId}`
			);
		}
	} catch (error) {
		debugError(
			`[Contextual Content] Error processing contextual content for prompt ${promptId}:`,
			error
		);
	}
};

/**
 * Extract prompt ID from the prompt element.
 *
 * @param prompt The prompt element.
 * @return The prompt ID or null if not found.
 */
const extractPromptId = (prompt: HTMLElement): number | null => {
	debugLog('[Contextual Content] Extracting prompt ID from element:', {
		id: prompt.getAttribute('id'),
		dataId: prompt.getAttribute('data-id'),
		classes: prompt.className,
	});

	// Try to get ID from element ID attribute (format: id_1234).
	const id = prompt.getAttribute('id');
	if (id) {
		const match = id.match(/^id_(\d+)$/);
		if (match) {
			const promptId = parseInt(match[1], 10);
			debugLog(
				'[Contextual Content] Found prompt ID in element ID:',
				promptId
			);
			return promptId;
		}
	}

	// Try data attribute.
	const dataId = prompt.getAttribute('data-id');
	if (dataId) {
		const promptId = parseInt(dataId, 10);
		debugLog(
			'[Contextual Content] Found prompt ID in data-id attribute:',
			promptId
		);
		return promptId;
	}

	// Try class names for legacy formats.
	const classList = prompt.classList.toString();
	const classMatch = classList.match(/newspack-popup-id-(\d+)/);
	if (classMatch) {
		const promptId = parseInt(classMatch[1], 10);
		debugLog(
			'[Contextual Content] Found prompt ID in class name:',
			promptId
		);
		return promptId;
	}

	debugWarn('[Contextual Content] Could not find prompt ID in element');
	return null;
};
