/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

(function () {
	'use strict';

	function initializeVerificationTargetSelectors() {
		var form = document.getElementById('emergencyhouse-verification-form');
		if (!form || form.getAttribute('data-target-locked') === '1') {
			return;
		}

		var objectTypeSelect = document.getElementById('object_type');
		var targetSelect = document.getElementById('fk_object');
		var refreshUrl = form.getAttribute('data-refresh-url');
		if (!objectTypeSelect || !targetSelect || !refreshUrl) {
			return;
		}

		objectTypeSelect.addEventListener('change', function () {
			targetSelect.value = '';
			targetSelect.disabled = true;

			var url = new URL(refreshUrl, window.location.origin);
			url.searchParams.set('target_mode', 'selector');
			url.searchParams.set('object_type', objectTypeSelect.value);
			window.location.assign(url.toString());
		});

		targetSelect.addEventListener('change', function () {
			if (targetSelect.value === '') {
				return;
			}

			var url = new URL(refreshUrl, window.location.origin);
			url.searchParams.set('target_mode', 'selector');
			url.searchParams.set('object_type', objectTypeSelect.value);
			url.searchParams.set('fk_object', targetSelect.value);
			window.location.assign(url.toString());
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeVerificationTargetSelectors);
	} else {
		initializeVerificationTargetSelectors();
	}
}());
