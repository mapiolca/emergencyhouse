<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!headers_sent()) {
	header('Content-Type: application/javascript; charset=UTF-8');
	header('Cache-Control: public, max-age=3600');
}
?>
"use strict";

document.addEventListener("DOMContentLoaded", function () {
	if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
		window.jQuery(".eh-select2").each(function () {
			var element = window.jQuery(this);
			element.select2({
				width: "100%",
				minimumResultsForSearch: element.find("option").length > 7 ? 0 : Infinity
			});
		});
	}

	document.querySelectorAll("[data-password-toggle]").forEach(function (button) {
		button.addEventListener("click", function () {
			var targetId = button.getAttribute("data-password-toggle");
			var input = targetId ? document.getElementById(targetId) : null;
			if (!input) return;
			var reveal = input.type === "password";
			input.type = reveal ? "text" : "password";
			button.setAttribute("aria-pressed", reveal ? "true" : "false");
		});
	});

	document.querySelectorAll("[data-character-count]").forEach(function (input) {
		var targetId = input.getAttribute("data-character-count");
		var target = targetId ? document.getElementById(targetId) : null;
		if (!target) return;
		var update = function () {
			target.textContent = String(input.value.length);
		};
		input.addEventListener("input", update);
		update();
	});

	document.querySelectorAll("[data-captcha-refresh]").forEach(function (button) {
		button.addEventListener("click", function () {
			var image = document.getElementById("img_securitycode");
			var input = document.getElementById("securitycode");
			var baseUrl = button.getAttribute("data-captcha-url");
			if (!image || !baseUrl) return;
			image.src = baseUrl + (baseUrl.indexOf("?") === -1 ? "?" : "&") + "refresh=" + String(Date.now());
			if (input) {
				input.value = "";
				input.focus();
			}
		});
	});

	document.querySelectorAll("form[data-disable-on-submit]").forEach(function (form) {
		form.addEventListener("submit", function () {
			form.querySelectorAll("button[type=submit], input[type=submit]").forEach(function (button) {
				button.disabled = true;
				button.setAttribute("aria-disabled", "true");
			});
		});
	});
});
