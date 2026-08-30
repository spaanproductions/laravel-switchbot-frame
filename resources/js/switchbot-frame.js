/**
 * SwitchBot Frame — client-side HEIC → JPEG conversion.
 *
 * The e-ink optimizer is pure GD and cannot decode HEIC, and no desktop browser
 * converts HEIC on its own. So we intercept a HEIC picked in either upload input,
 * transcode it to JPEG in the browser (heic2any / libheif-wasm, lazy-loaded on the
 * first HEIC pick), and hand the JPEG to Livewire's normal upload. The server only
 * ever receives a JPEG.
 *
 * If conversion cannot run (JS disabled, or a host CSP blocks the WASM), the raw
 * HEIC reaches the server and is rejected by the `mimes` validation rule — never a
 * 500 (the blade preview is guarded with isPreviewable()).
 */
(function () {
	'use strict';

	var HEIC_NAME = /\.hei[cf]$/i;
	var HEIC_TYPE = /^image\/hei[cf]$/i;
	var converterPromise = null;

	function isHeic(file) {
		return !! file && (HEIC_TYPE.test(file.type) || HEIC_NAME.test(file.name));
	}

	function isLivewireFileInput(el) {
		if ( ! (el instanceof HTMLInputElement) || el.type !== 'file') {
			return false;
		}

		return el.getAttributeNames().some(function (name) {
			return name.indexOf('wire:model') === 0;
		});
	}

	function loadConverter() {
		if (window.heic2any) {
			return Promise.resolve(window.heic2any);
		}

		if ( ! converterPromise) {
			converterPromise = new Promise(function (resolve, reject) {
				var url = (window.SwitchbotFrame || {}).heicConverterUrl;

				if ( ! url) {
					reject(new Error('SwitchbotFrame.heicConverterUrl is not set.'));

					return;
				}

				var script = document.createElement('script');
				script.src = url;
				script.onload = function () {
					window.heic2any
						? resolve(window.heic2any)
						: reject(new Error('The HEIC converter did not load.'));
				};
				script.onerror = function () {
					converterPromise = null;
					reject(new Error('Failed to load the HEIC converter.'));
				};

				document.head.appendChild(script);
			});
		}

		return converterPromise;
	}

	function emit(target, name, detail) {
		target.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} }));
	}

	function convertAndUpload(input, file) {
		emit(input, 'switchbot:heic-converting', { name: file.name });

		loadConverter()
			.then(function (heic2any) {
				return heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 });
			})
			.then(function (result) {
				// Multi-image HEIC (live photos / bursts) resolves to an array.
				var blob = Array.isArray(result) ? result[0] : result;
				var jpeg = new File([blob], file.name.replace(HEIC_NAME, '.jpg'), { type: 'image/jpeg' });

				var data = new DataTransfer();
				data.items.add(jpeg);
				input.files = data.files;

				emit(input, 'switchbot:heic-done', { name: jpeg.name });

				// Let Livewire's own change handler pick up the JPEG we just swapped in.
				input.dispatchEvent(new Event('change', { bubbles: true }));
			})
			.catch(function (error) {
				// Reset so the same file can be re-picked, and surface the failure.
				input.value = '';
				emit(input, 'switchbot:heic-error', {
					message: (error && error.message) || String(error),
				});
			});
	}

	// Capture phase so we run before Livewire's own change listener and can stop it.
	document.addEventListener('change', function (event) {
		var input = event.target;

		if ( ! isLivewireFileInput(input)) {
			return;
		}

		var file = input.files && input.files[0];

		if ( ! isHeic(file)) {
			return; // JPEG / PNG / WebP flow through to Livewire untouched.
		}

		// Block Livewire from uploading the raw HEIC; we re-dispatch a JPEG instead.
		event.preventDefault();
		event.stopImmediatePropagation();

		convertAndUpload(input, file);
	}, true);
})();
