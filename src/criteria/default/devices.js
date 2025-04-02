import { setMatchingFunction } from '../utils';

setMatchingFunction('devices', (config) => {
	const selectedDevices = Array.isArray(config.value) ? config.value : [];

	if (selectedDevices.length === 0) {
		return false;
	}

	const width = window.innerWidth;
	// On desktop if the screen is wide enough, no need for more checks.
	if (width > 1280 && selectedDevices.includes('desktop')) {
		return true;
	}

	const isPortrait = window.innerHeight > width;
	const orientationMode = isPortrait ? 'portrait' : 'landscape';

	const breakpoints = {
		mobile_small: {
			portrait: { min: 0, max: 360 },
			landscape: { min: 0, max: 640 },
		},
		mobile: {
			portrait: { min: 361, max: 480 },
			landscape: { min: 641, max: 768 },
		},
		tablet: {
			portrait: { min: 481, max: 768 },
			landscape: { min: 769, max: 1024 },
		},
		laptop: {
			portrait: { min: 769, max: 1024 },
			landscape: { min: 1025, max: 1280 },
		},
	};

	for (const device of selectedDevices) {
		if (!breakpoints[device]) {
			continue;
		}

		const { min, max } = breakpoints[device][orientationMode];
		if (width >= min && width <= max) {
			return true;
		}
	}
	return false;
});
