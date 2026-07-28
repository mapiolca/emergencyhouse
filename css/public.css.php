<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!headers_sent()) {
	header('Content-Type: text/css; charset=UTF-8');
	header('Cache-Control: public, max-age=3600');
}
?>
:root {
	--eh-ink: #17242c;
	--eh-muted: #5c6b73;
	--eh-paper: #ffffff;
	--eh-canvas: #f5f7f6;
	--eh-border: #d8e0dd;
	--eh-primary: #0c6b58;
	--eh-primary-dark: #084c40;
	--eh-accent: #ef8153;
	--eh-success: #237a50;
	--eh-warning: #9c5b08;
	--eh-danger: #a52d2d;
	--eh-radius: 18px;
	--eh-shadow: 0 14px 42px rgba(23, 36, 44, .1);
	--eh-shell: 1180px;
	font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	color: var(--eh-ink);
	background: var(--eh-canvas);
	line-height: 1.5;
}

* { box-sizing: border-box; }
html { min-width: 320px; scroll-behavior: smooth; }
body.eh-public { margin: 0; min-width: 320px; background: var(--eh-canvas); color: var(--eh-ink); font-size: 16px; }
.eh-public img { max-width: 100%; height: auto; }
.eh-public a { color: var(--eh-primary-dark); text-underline-offset: .2em; }
.eh-public button, .eh-public input, .eh-public select, .eh-public textarea { font: inherit; }
.eh-public input, .eh-public select, .eh-public textarea { font-size: 16px; }
.eh-public button, .eh-public a, .eh-public input, .eh-public select, .eh-public textarea { touch-action: manipulation; }
.eh-public :focus-visible { outline: 3px solid #236ba4; outline-offset: 3px; }
.eh-shell { width: min(calc(100% - 32px), var(--eh-shell)); margin-inline: auto; }
.eh-section { padding-block: clamp(28px, 6vw, 72px); }
.eh-section-tight { padding-block: 28px; }
.eh-skip-link { position: fixed; z-index: 100; top: 8px; left: 8px; transform: translateY(-150%); padding: 12px 16px; background: var(--eh-paper); border-radius: 8px; }
.eh-skip-link:focus { transform: none; }

.eh-site-header { position: sticky; z-index: 20; top: 0; background: rgba(255,255,255,.96); border-bottom: 1px solid var(--eh-border); backdrop-filter: blur(14px); }
.eh-header-inner { display: flex; min-height: 76px; align-items: center; justify-content: space-between; gap: 24px; }
.eh-brand { display: inline-flex; align-items: center; gap: 10px; color: var(--eh-ink); text-decoration: none; }
.eh-brand span { display: flex; flex-direction: column; line-height: 1.15; }
.eh-brand small { margin-top: 4px; color: var(--eh-muted); font-size: 12px; font-weight: 500; }
.eh-site-header nav ul, .eh-site-footer nav ul { display: flex; align-items: center; gap: 6px; margin: 0; padding: 0; list-style: none; }
.eh-nav-link { display: inline-flex; min-height: 44px; align-items: center; padding: 9px 13px; border-radius: 999px; color: var(--eh-ink); font-weight: 650; text-decoration: none; }
.eh-nav-link:hover, .eh-nav-link.is-active { background: #e8f2ef; }
.eh-nav-cta { background: var(--eh-primary); color: #fff !important; }
.eh-nav-cta:hover { background: var(--eh-primary-dark); }

.eh-hero { position: relative; overflow: hidden; padding-block: clamp(42px, 8vw, 96px); background: linear-gradient(135deg, #e6f4ef 0%, #fff4ec 100%); }
.eh-hero::after { content: ""; position: absolute; right: -80px; bottom: -140px; width: 380px; height: 380px; border-radius: 50%; background: rgba(239,129,83,.16); }
.eh-hero-grid { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr); align-items: center; gap: clamp(32px, 6vw, 80px); }
.eh-eyebrow { margin: 0 0 10px; color: var(--eh-primary-dark); font-size: 14px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.eh-hero h1, .eh-page-title h1 { max-width: 18ch; margin: 0; font-size: clamp(34px, 6vw, 64px); line-height: 1.02; letter-spacing: -.035em; }
.eh-lead { max-width: 65ch; margin: 20px 0 0; color: #3d4b52; font-size: clamp(18px, 2vw, 21px); }
.eh-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }
.eh-button { display: inline-flex; min-height: 48px; align-items: center; justify-content: center; gap: 8px; padding: 11px 19px; border: 1px solid transparent; border-radius: 12px; background: var(--eh-primary); color: #fff !important; font-weight: 750; text-decoration: none; cursor: pointer; }
.eh-button:hover { background: var(--eh-primary-dark); }
.eh-button-secondary { border-color: var(--eh-border); background: var(--eh-paper); color: var(--eh-ink) !important; }
.eh-button-secondary:hover { background: #edf3f1; }
.eh-button-danger { background: var(--eh-danger); }
.eh-button-small { min-height: 40px; padding: 8px 13px; font-size: 14px; }
.eh-button[disabled] { opacity: .55; cursor: not-allowed; }
.eh-reassurance { display: grid; gap: 12px; padding: 24px; border: 1px solid rgba(255,255,255,.7); border-radius: var(--eh-radius); background: rgba(255,255,255,.72); box-shadow: var(--eh-shadow); }
.eh-reassurance-item { display: grid; grid-template-columns: 38px 1fr; gap: 12px; align-items: start; }
.eh-reassurance-icon { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; background: #d8eee7; color: var(--eh-primary-dark); font-weight: 900; }

.eh-page-title { padding-block: clamp(30px, 5vw, 58px) 24px; }
.eh-page-title h1 { max-width: 24ch; font-size: clamp(30px, 5vw, 48px); }
.eh-page-title p { max-width: 70ch; color: var(--eh-muted); font-size: 18px; }
.eh-section-heading { display: flex; align-items: end; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
.eh-section-heading h2 { margin: 0; font-size: clamp(25px, 4vw, 36px); line-height: 1.1; }
.eh-section-heading p { margin: 7px 0 0; color: var(--eh-muted); }

.eh-card-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
.eh-card { min-width: 0; padding: 22px; border: 1px solid var(--eh-border); border-radius: var(--eh-radius); background: var(--eh-paper); box-shadow: 0 8px 28px rgba(23,36,44,.06); }
.eh-card h2, .eh-card h3 { margin: 0 0 9px; line-height: 1.2; }
.eh-card p { margin: 8px 0; }
.eh-card-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: var(--eh-muted); font-size: 14px; }
.eh-card-link { color: inherit !important; text-decoration: none; }
.eh-card-link::after { content: ""; }
.eh-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--eh-border); }
.eh-photo-manager, .eh-offer-gallery { margin-bottom: 24px; }
.eh-photo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; }
.eh-photo-card { min-width: 0; margin: 0; overflow: hidden; border: 1px solid var(--eh-border); border-radius: 14px; background: var(--eh-paper); }
.eh-photo-card img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #edf3f1; }
.eh-photo-card figcaption { padding: 10px 12px; color: var(--eh-muted); font-size: 14px; font-weight: 700; }
.eh-photo-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; }
.eh-photo-card-footer form { margin: 0; }
.eh-legal-content { overflow-wrap: anywhere; }
.eh-legal-content > :first-child { margin-top: 0; }
.eh-legal-content > :last-child { margin-bottom: 0; }
.eh-legal-content table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
.eh-legal-content th, .eh-legal-content td { padding: 8px; border: 1px solid var(--eh-border); text-align: left; vertical-align: top; }
.eh-badge { display: inline-flex; min-height: 28px; align-items: center; padding: 4px 10px; border-radius: 999px; background: #e7efed; color: #28473f; font-size: 13px; font-weight: 750; }
.eh-badge-urgent { background: #f8dfdb; color: #81271f; }
.eh-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.eh-stat { padding: 20px; border: 1px solid var(--eh-border); border-radius: 14px; background: var(--eh-paper); }
.eh-stat strong { display: block; font-size: clamp(27px, 4vw, 40px); line-height: 1; }
.eh-stat span { display: block; margin-top: 8px; color: var(--eh-muted); }

.eh-form { max-width: 840px; padding: clamp(20px, 4vw, 38px); border: 1px solid var(--eh-border); border-radius: var(--eh-radius); background: var(--eh-paper); box-shadow: var(--eh-shadow); }
.eh-form-wide { max-width: none; }
.eh-form-section + .eh-form-section { margin-top: 34px; padding-top: 28px; border-top: 1px solid var(--eh-border); }
.eh-form-section h2 { margin: 0 0 6px; font-size: 24px; }
.eh-form-section > p { margin: 0 0 20px; color: var(--eh-muted); }
.eh-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.eh-field { min-width: 0; }
.eh-field-full { grid-column: 1 / -1; }
.eh-field label, .eh-field legend { display: block; margin-bottom: 7px; font-weight: 750; }
.eh-field input:not([type="checkbox"]):not([type="radio"]), .eh-field select, .eh-field textarea {
	width: 100%;
	min-height: 50px;
	padding: 11px 13px;
	border: 1px solid #aebbb6;
	border-radius: 10px;
	background: #fff;
	color: var(--eh-ink);
}
.eh-field textarea { min-height: 128px; resize: vertical; }
.eh-field input:focus, .eh-field select:focus, .eh-field textarea:focus { border-color: var(--eh-primary); box-shadow: 0 0 0 4px rgba(12,107,88,.12); }
.eh-field .select2-container { width: 100% !important; }
.eh-field .select2-container .select2-selection--single,
.eh-field .select2-container .select2-selection--multiple {
	min-height: 50px;
	border: 1px solid #aebbb6;
	border-radius: 10px;
	background: #fff;
}
.eh-field .select2-container .select2-selection--single .select2-selection__rendered {
	padding: 10px 40px 10px 13px;
	color: var(--eh-ink);
	line-height: 28px;
}
.eh-field .select2-container .select2-selection--single .select2-selection__arrow { top: 11px; right: 10px; }
.eh-field .select2-container--focus .select2-selection,
.eh-field .select2-container--open .select2-selection {
	border-color: var(--eh-primary);
	box-shadow: 0 0 0 4px rgba(12,107,88,.12);
}
.select2-dropdown { border-color: #aebbb6; }
.eh-help { display: block; margin-top: 6px; color: var(--eh-muted); font-size: 14px; }
.eh-fieldset { margin: 0; padding: 0; border: 0; }
.eh-choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.eh-choice { position: relative; display: flex; min-height: 52px; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--eh-border); border-radius: 10px; background: #fff; }
.eh-choice input { width: 22px; height: 22px; margin: 0; accent-color: var(--eh-primary); }
.eh-switch { display: flex; align-items: center; justify-content: space-between; gap: 14px; min-height: 52px; padding: 10px 12px; border: 1px solid var(--eh-border); border-radius: 10px; }
.eh-switch input { width: 46px; height: 26px; accent-color: var(--eh-primary); }
.eh-form-actions { position: sticky; bottom: 0; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin: 30px -12px -12px; padding: 14px 12px calc(14px + env(safe-area-inset-bottom)); border-top: 1px solid var(--eh-border); background: rgba(255,255,255,.96); backdrop-filter: blur(10px); }
.eh-contact-layout { display: grid; grid-template-columns: minmax(240px, .65fr) minmax(0, 1.35fr); align-items: start; gap: 24px; }
.eh-contact-details { position: sticky; top: 96px; }
.eh-contact-link { overflow-wrap: anywhere; font-size: 18px; font-weight: 750; }
.eh-captcha { display: grid; grid-template-columns: minmax(120px, 1fr) auto auto; align-items: center; gap: 10px; }
.eh-captcha img { width: 80px; max-width: none; height: 32px; border: 1px solid var(--eh-border); border-radius: 6px; background: #fafafa; image-rendering: auto; }
.eh-field input[type="file"] { min-height: 54px; padding: 9px; }
.eh-field input[type="file"]::file-selector-button { min-height: 34px; margin-right: 10px; padding: 6px 10px; border: 1px solid var(--eh-border); border-radius: 8px; background: #edf3f1; color: var(--eh-ink); cursor: pointer; }

.eh-alert { margin-block: 16px; padding: 14px 16px; border: 1px solid; border-radius: 12px; }
.eh-alert-info { border-color: #9cbfd9; background: #edf6fc; color: #174968; }
.eh-alert-success { border-color: #9dc9b2; background: #edf8f1; color: #18583a; }
.eh-alert-warning { border-color: #e3bd81; background: #fff6e8; color: #774204; }
.eh-alert-error { border-color: #dda4a4; background: #fff0f0; color: #7f2020; }
.eh-empty { padding: 30px; border: 1px dashed #afbbb7; border-radius: var(--eh-radius); background: rgba(255,255,255,.6); text-align: center; }
.eh-toolbar { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; padding: 14px; border: 1px solid var(--eh-border); border-radius: 14px; background: var(--eh-paper); }
.eh-toolbar .eh-field { flex: 1 1 180px; }

.eh-dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 22px; }
.eh-list { margin: 0; padding: 0; list-style: none; }
.eh-list > li + li { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--eh-border); }
.eh-description-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin: 0; }
.eh-description-list > div { padding: 13px; border: 1px solid var(--eh-border); border-radius: 10px; background: #f8faf9; }
.eh-description-list dt { color: var(--eh-muted); font-size: 13px; font-weight: 750; text-transform: uppercase; }
.eh-description-list dd { margin: 5px 0 0; font-weight: 650; }
.eh-tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
.eh-preference-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.eh-pagination { display: flex; justify-content: center; margin-top: 28px; }
.eh-list-cards { display: grid; gap: 16px; }
.eh-list-card { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eh-list-card h2, .eh-list-card p { margin-bottom: 6px; margin-top: 0; }
.eh-inline-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--eh-border); }
.eh-inline-form:first-of-type { margin-top: 0; padding-top: 0; border-top: 0; }
.eh-message-thread { display: flex; max-height: 560px; flex-direction: column; gap: 12px; overflow: auto; padding: 16px; border: 1px solid var(--eh-border); border-radius: 14px; background: #eef3f1; }
.eh-message { max-width: min(78%, 620px); padding: 12px 14px; border-radius: 14px 14px 14px 4px; background: #fff; white-space: pre-wrap; }
.eh-message-own { align-self: flex-end; border-radius: 14px 14px 4px 14px; background: #d9efe8; }
.eh-site-footer { margin-top: 44px; padding-block: 34px calc(34px + env(safe-area-inset-bottom)); border-top: 1px solid var(--eh-border); background: #e9efed; }
.eh-footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 30px; }
.eh-site-footer p { max-width: 70ch; color: var(--eh-muted); }
.eh-site-footer nav ul { align-items: start; flex-direction: column; }
.eh-footer-contact { display: flex; align-items: start; flex-direction: column; gap: 6px; margin: 10px 0 0; padding: 0; list-style: none; }

@media (max-width: 900px) {
	.eh-header-inner { align-items: flex-start; flex-direction: column; gap: 12px; padding-block: 12px; }
	.eh-site-header { position: static; }
	.eh-site-header nav { width: 100%; }
	.eh-site-header nav ul { flex-wrap: wrap; }
	.eh-hero-grid, .eh-dashboard-grid { grid-template-columns: 1fr; }
	.eh-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.eh-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.eh-preference-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.eh-footer-grid { grid-template-columns: 2fr 1fr; }
}

@media (max-width: 640px) {
	.eh-shell { width: min(calc(100% - 24px), var(--eh-shell)); }
	.eh-brand small { display: none; }
	.eh-card-grid, .eh-field-grid, .eh-choice-grid, .eh-footer-grid, .eh-description-list, .eh-preference-grid, .eh-contact-layout { grid-template-columns: 1fr; }
	.eh-field-full { grid-column: auto; }
	.eh-stat-grid { grid-template-columns: 1fr 1fr; }
	.eh-section-heading { align-items: start; flex-direction: column; }
	.eh-form { padding: 18px; }
	.eh-form-actions { justify-content: stretch; }
	.eh-form-actions .eh-button { flex: 1 1 100%; }
	.eh-message { max-width: 90%; }
	.eh-contact-details { position: static; }
	.eh-captcha { grid-template-columns: 1fr auto; }
	.eh-captcha .eh-button { grid-column: 1 / -1; }
}

@media (prefers-reduced-motion: reduce) {
	*, *::before, *::after { scroll-behavior: auto !important; transition: none !important; animation: none !important; }
}

@media (forced-colors: active) {
	.eh-button, .eh-card, .eh-form, .eh-alert { border: 1px solid CanvasText; }
}
