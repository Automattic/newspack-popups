import {setMatchingFunction} from '../utils';

setMatchingFunction('devices', (config) => {
	const selectedDevices = Array.isArray(config.value) ? config.value : [];
	if (selectedDevices.length === 0) {
		return false;
	}

	const width = window.innerWidth;
	if (width >= 1280 && selectedDevices.includes('desktop')) {
		return true;
	}
	if (width <= 1024 && width > 768 && selectedDevices.includes('laptop')) {
		return true;
	}
	if (width <= 768 && width > 480 && selectedDevices.includes('tablet')) {
		return true;
	}
	if (width <= 480 && width > 360 && selectedDevices.includes('mobile')) {
		return true;
	}
	if (width < 360 && selectedDevices.includes('mobile_small')) {
		return true;
	}

	return false;
});
