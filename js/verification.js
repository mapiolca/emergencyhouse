/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

(function () {
	'use strict';

	function formatDuration(totalSeconds) {
		var seconds = Math.max(0, Math.floor(totalSeconds));
		var hours = Math.floor(seconds / 3600);
		var minutes = Math.floor((seconds % 3600) / 60);
		var remainingSeconds = seconds % 60;

		return String(hours).padStart(2, '0')
			+ ':' + String(minutes).padStart(2, '0')
			+ ':' + String(remainingSeconds).padStart(2, '0');
	}

	function renderAge(element, elapsed) {
		var warning = Number(element.dataset.warning || 600);
		var critical = Number(element.dataset.critical || 1800);
		var value = element.querySelector('.emergencyhouse-verification-age__value');
		element.classList.remove(
			'emergencyhouse-verification-age--warning',
			'emergencyhouse-verification-age--critical'
		);
		if (elapsed >= critical) {
			element.classList.add('emergencyhouse-verification-age--critical');
		} else if (elapsed >= warning) {
			element.classList.add('emergencyhouse-verification-age--warning');
		}
		if (value) {
			value.textContent = formatDuration(elapsed);
		}
	}

	var ages = Array.prototype.slice.call(
		document.querySelectorAll('.emergencyhouse-verification-age')
	).map(function (element) {
		return {
			element: element,
			initial: Number(element.dataset.elapsed || 0),
			startedAt: Date.now()
		};
	});

	function refresh() {
		ages.forEach(function (age) {
			var elapsed = age.initial + Math.floor((Date.now() - age.startedAt) / 1000);
			renderAge(age.element, elapsed);
		});
	}

	refresh();
	if (ages.length > 0) {
		window.setInterval(refresh, 1000);
	}
}());
