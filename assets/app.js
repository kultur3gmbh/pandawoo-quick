(function () {
	'use strict';

	var config = window.PANDAWOO_QUICK || {};
	var root = document.querySelector('.pwq');
	if (!root) return;

	var listView = root.querySelector('.pwq-list-view');
	var editorView = root.querySelector('.pwq-editor-view');
	var list = root.querySelector('.pwq-product-list');
	var listMessage = root.querySelector('.pwq-list-message');
	var search = root.querySelector('.pwq-product-search');
	var statusFilter = root.querySelector('.pwq-status-filter');
	var moreButton = root.querySelector('.pwq-more');
	var newButton = root.querySelector('.pwq-new');
	var disconnectButton = root.querySelector('.pwq-disconnect');
	var installButton = root.querySelector('.pwq-install-app');
	var installFallback = root.querySelector('.pwq-install-fallback');
	var pairingPanel = root.querySelector('.pwq-pair');
	var installGuide = root.querySelector('.pwq-install-guide');
	var backButton = root.querySelector('.pwq-back');
	var form = root.querySelector('.pwq-form');
	var editorTitle = root.querySelector('.pwq-editor-title');
	var imageInput = root.querySelector('.pwq-images');
	var photoList = root.querySelector('.pwq-photo-list');
	var photoSummary = root.querySelector('.pwq-photo-summary');
	var categorySearch = root.querySelector('.pwq-category-search');
	var categorySelect = root.querySelector('.pwq-category');
	var brandSelect = root.querySelector('.pwq-brand');
	var stockMode = root.querySelector('.pwq-stock-mode');
	var stockQuantity = root.querySelector('.pwq-stock-quantity');
	var stockQuantityInput = stockQuantity.querySelector('input');
	var schedule = root.querySelector('.pwq-schedule');
	var scheduleInput = schedule.querySelector('input');
	var message = root.querySelector('.pwq-message');
	var deleteButton = root.querySelector('.pwq-delete-product');
	var qr = root.querySelector('.pwq-qr');
	var page = 1;
	var pages = 1;
	var debounceTimer = 0;
	var photos = [];
	var nextPhotoId = 1;
	var drag = null;
	var installPrompt = null;
	var pairPoll = 0;
	var pairPollAttempts = 0;
	var categoryOptions = Array.prototype.map.call(categorySelect.options, function (option) {
		return { value: option.value, label: option.textContent };
	});

	function request(action, data, files) {
		var body;
		if (files) {
			body = data;
		} else {
			body = new URLSearchParams();
			Object.keys(data || {}).forEach(function (key) { body.append(key, data[key]); });
		}
		body.append('action', 'pandawoo_quick_' + action);
		body.append('nonce', config.nonce || '');
		body.append('device_nonce', config.deviceNonce || '');
		return fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (response) {
			return response.text().then(function (text) {
				var json;
				try {
					json = JSON.parse(text);
				} catch (error) {
					reportClientError(action, response.status);
					throw new Error(config.i18n.invalidResponse || config.i18n.network);
				}
				if (!response.ok || !json.success) throw new Error((json.data && json.data.message) || config.i18n.network);
				return json.data;
			});
		});
	}

	function reportClientError(action, status) {
		var body = new URLSearchParams();
		body.append('action', 'pandawoo_quick_client_error');
		body.append('nonce', config.nonce || '');
		body.append('device_nonce', config.deviceNonce || '');
		body.append('operation', action || 'unknown');
		body.append('status', String(status || 0));
		fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
	}

	function escapeHtml(value) {
		var node = document.createElement('span');
		node.textContent = value == null ? '' : String(value);
		return node.innerHTML;
	}

	function statusLabel(status) {
		return { publish: config.i18n.statusPublished, draft: config.i18n.statusDraft, future: config.i18n.statusScheduled, pending: config.i18n.statusPending, private: config.i18n.statusPrivate }[status] || status;
	}

	function detectPlatform() {
		var ua = navigator.userAgent || '';
		if (/iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) return 'ios';
		if (/Android/i.test(ua)) return 'android';
		return 'unknown';
	}

	function isStandalone() {
		return !!window.navigator.standalone || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
	}

	function reportDevice() {
		if (!config.deviceNonce) return;
		request('report_device', { platform: detectPlatform() }).catch(function () {});
	}

	function guideStep(number, image, title, text) {
		return '<article class="pwq-guide-step"><span class="pwq-guide-number">' + number + '</span><img src="' + escapeHtml(config.assetUrl + image) + '" alt=""><div><h3>' + escapeHtml(title) + '</h3><p>' + escapeHtml(text) + '</p></div></article>';
	}

	function renderInstallGuide(platform) {
		if (!installGuide || !pairingPanel) return;
		pairingPanel.hidden = true;
		installGuide.hidden = false;
		var html = '<div class="pwq-guide-head"><div><p class="pwq-kicker">' + escapeHtml(config.i18n.phoneConnected) + '</p><h2>' + escapeHtml(platform === 'android' ? config.i18n.installAndroidTitle : platform === 'ios' ? config.i18n.installIosTitle : config.i18n.installGenericTitle) + '</h2><p>' + escapeHtml(config.i18n.keepGuideOpen) + '</p></div><span class="pwq-guide-device">' + escapeHtml(platform === 'android' ? 'Android' : platform === 'ios' ? 'iPhone / iPad' : config.i18n.smartphone) + '</span></div><div class="pwq-guide-steps">';
		if (platform === 'ios') {
			html += guideStep(1, 'guide-ios-share.svg', config.i18n.iosShareTitle, config.i18n.iosShareText);
			html += guideStep(2, 'guide-ios-home.svg', config.i18n.iosHomeTitle, config.i18n.iosHomeText);
			html += guideStep(3, 'guide-ios-webapp.svg', config.i18n.iosWebAppTitle, config.i18n.iosWebAppText);
			html += guideStep(4, 'guide-ios-add.svg', config.i18n.iosAddTitle, config.i18n.iosAddText);
			html += guideStep(5, 'guide-ios-finished.svg', config.i18n.finishedTitle, config.i18n.iosFinishedText);
		} else if (platform === 'android') {
			html += guideStep(1, 'guide-android-install.svg', config.i18n.androidInstallTitle, config.i18n.androidInstallText);
			html += guideStep(2, 'guide-android-finished.svg', config.i18n.androidOpenTitle, config.i18n.androidOpenText);
		} else {
			html += guideStep(1, 'guide-generic.svg', config.i18n.genericMenuTitle, config.i18n.genericMenuText);
		}
		installGuide.innerHTML = html + '</div><p class="pwq-guide-help">' + escapeHtml(config.i18n.installOptional) + '</p>';
		installGuide.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function pollPairStatus() {
		if (!pairingPanel || !pairingPanel.dataset.pairingId) return;
		var state = pairingPanel.querySelector('.pwq-pair-state');
		request('pair_status', { pairing_id: pairingPanel.dataset.pairingId }).then(function (data) {
			pairPollAttempts += 1;
			if (data.status === 'connected') {
				clearInterval(pairPoll);
				renderInstallGuide(data.platform || 'unknown');
			} else if (data.status === 'identifying' && pairPollAttempts >= 8) {
				clearInterval(pairPoll);
				renderInstallGuide('unknown');
			} else if (data.status === 'expired') {
				clearInterval(pairPoll);
				if (state) state.textContent = config.i18n.pairExpired;
			}
		}).catch(function () {});
	}

	function loadProducts(reset) {
		if (reset) { page = 1; list.innerHTML = ''; }
		listMessage.textContent = config.i18n.loading;
		request('list', { page: page, search: search.value.trim(), status: statusFilter.value }).then(function (data) {
			pages = data.pages || 1;
			if (!data.products.length && page === 1) list.innerHTML = '<div class="pwq-empty">' + escapeHtml(config.i18n.noProducts) + '</div>';
			data.products.forEach(renderProductCard);
			listMessage.textContent = '';
			moreButton.hidden = page >= pages;
		}).catch(function (error) {
			listMessage.textContent = error.message;
		});
	}

	function renderProductCard(product) {
		var article = document.createElement('article');
		article.className = 'pwq-product';
		article.tabIndex = 0;
		article.dataset.id = product.id;
		var image = product.images[0] ? '<img src="' + escapeHtml(product.images[0].url) + '" alt="">' : '<span class="pwq-image-placeholder">◇</span>';
		article.innerHTML = '<div class="pwq-product-image">' + image + '</div><div class="pwq-product-copy"><span class="pwq-status is-' + escapeHtml(product.status) + '">' + escapeHtml(statusLabel(product.status)) + '</span><h2>' + escapeHtml(product.name) + '</h2><strong>' + escapeHtml(String(product.price).replace('.', ',')) + ' ' + escapeHtml(config.currency) + '</strong></div><span class="pwq-chevron">›</span>';
		article.addEventListener('click', function () { openExisting(product.id); });
		article.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openExisting(product.id); } });
		list.appendChild(article);
	}

	function showEditor(show) {
		listView.hidden = show;
		editorView.hidden = !show;
		newButton.hidden = show;
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	function releasePhotos() {
		photos.forEach(function (photo) { if (photo.isNew && photo.url) URL.revokeObjectURL(photo.url); });
		photos = [];
	}

	function resetEditor() {
		releasePhotos();
		form.reset();
		form.elements.product_id.value = '0';
		editorTitle.textContent = config.i18n.newProduct;
		schedule.hidden = true;
		scheduleInput.required = false;
		stockMode.value = 'instock';
		stockQuantity.hidden = true;
		stockQuantityInput.required = false;
		stockQuantityInput.value = '0';
		deleteButton.hidden = true;
		message.textContent = '';
		categorySearch.value = '';
		filterCategories();
		renderPhotos();
	}

	function openNew() {
		resetEditor();
		showEditor(true);
	}

	function openExisting(id) {
		showEditor(true);
		editorTitle.textContent = config.i18n.editProduct;
		message.textContent = config.i18n.loading;
		request('get', { product_id: id }).then(function (data) {
			var product = data.product;
			resetEditor();
			form.elements.product_id.value = product.id;
			form.elements.product_name.value = product.name;
			form.elements.regular_price.value = product.price;
			form.elements.short_description.value = product.shortDescription;
			form.elements.description.value = product.description;
			stockMode.value = product.stockMode || 'instock';
			stockQuantityInput.value = String(product.stockQuantity || 0);
			toggleStockQuantity();
			categorySelect.value = product.categoryId;
			if (brandSelect) brandSelect.value = product.brandId || '';
			var mode = form.querySelector('[name="publish_mode"][value="' + product.status + '"]') || form.querySelector('[name="publish_mode"][value="draft"]');
			mode.checked = true;
			schedule.hidden = product.status !== 'future';
			scheduleInput.required = product.status === 'future';
			scheduleInput.value = product.publishAt || '';
			photos = product.images.map(function (image) { return { key: 'existing:' + image.id, id: image.id, url: image.url, isNew: false }; });
			deleteButton.hidden = false;
			editorTitle.textContent = config.i18n.editProduct;
			message.textContent = '';
			renderPhotos();
		}).catch(function (error) { message.textContent = error.message; });
	}

	function stableFileKey(file) { return [file.name, file.size, file.lastModified].join(':'); }

	function resizedName(name, mime) {
		var stem = String(name || 'product-photo').replace(/\.[^.]+$/, '');
		return stem + (mime === 'image/png' ? '.png' : '.jpg');
	}

	function preparePhoto(file) {
		var maximumEdge = 2048;
		var quality = 0.84;
		return new Promise(function (resolve) {
			var url = URL.createObjectURL(file);
			var image = new Image();
			image.onload = function () {
				URL.revokeObjectURL(url);
				var width = image.naturalWidth || image.width;
				var height = image.naturalHeight || image.height;
				if (!width || !height || Math.max(width, height) <= maximumEdge) { resolve({ blob: file, name: file.name }); return; }
				var scale = maximumEdge / Math.max(width, height);
				var canvas = document.createElement('canvas');
				canvas.width = Math.max(1, Math.round(width * scale));
				canvas.height = Math.max(1, Math.round(height * scale));
				var context = canvas.getContext('2d');
				if (!context) { resolve({ blob: file, name: file.name }); return; }
				context.drawImage(image, 0, 0, canvas.width, canvas.height);
				var mime = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
				canvas.toBlob(function (blob) {
					if (!blob) { resolve({ blob: file, name: file.name }); return; }
					resolve({ blob: blob, name: resizedName(file.name, mime) });
				}, mime, mime === 'image/png' ? undefined : quality);
			};
			image.onerror = function () { URL.revokeObjectURL(url); resolve({ blob: file, name: file.name }); };
			image.src = url;
		});
	}

	function addFiles(fileList) {
		var existing = {};
		photos.forEach(function (photo) { if (photo.file) existing[stableFileKey(photo.file)] = true; });
		Array.prototype.forEach.call(fileList || [], function (file) {
			if (photos.length >= 12 || !/^image\/(jpeg|png|webp)$/i.test(file.type) || existing[stableFileKey(file)]) return;
			var localId = 'p' + nextPhotoId++;
			photos.push({ key: 'new:' + localId, localId: localId, file: file, url: URL.createObjectURL(file), isNew: true });
			existing[stableFileKey(file)] = true;
		});
		imageInput.value = '';
		renderPhotos();
	}

	function capturePhotoRects() {
		var rects = {};
		Array.prototype.forEach.call(photoList.children, function (item) { rects[item.dataset.key] = item.getBoundingClientRect(); });
		return rects;
	}

	function movePhoto(from, to) {
		if (from === to || from < 0 || to < 0 || from >= photos.length || to >= photos.length) return;
		var previousRects = capturePhotoRects();
		var moved = photos.splice(from, 1)[0];
		photos.splice(to, 0, moved);
		renderPhotos(previousRects, moved.key);
	}

	function renderPhotos(previousRects, settledKey) {
		photoList.innerHTML = '';
		photos.forEach(function (photo, index) {
			var item = document.createElement('article');
			item.className = 'pwq-photo';
			item.dataset.index = String(index);
			item.dataset.key = photo.key;
			item.innerHTML = '<img src="' + escapeHtml(photo.url) + '" alt="">' + (index === 0 ? '<span class="pwq-cover">' + escapeHtml(config.i18n.cover) + '</span>' : '') + '<button type="button" class="pwq-handle" aria-label="' + escapeHtml(config.i18n.move) + '"><span aria-hidden="true">⠿</span></button><div class="pwq-photo-actions"><button type="button" class="pwq-earlier" aria-label="' + escapeHtml(config.i18n.previous) + '">←</button><button type="button" class="pwq-later" aria-label="' + escapeHtml(config.i18n.next) + '">→</button><button type="button" class="pwq-remove" aria-label="' + escapeHtml(config.i18n.remove) + '">×</button></div>';
			item.querySelector('.pwq-earlier').disabled = index === 0;
			item.querySelector('.pwq-later').disabled = index === photos.length - 1;
			item.querySelector('.pwq-earlier').addEventListener('click', function () { movePhoto(index, index - 1); });
			item.querySelector('.pwq-later').addEventListener('click', function () { movePhoto(index, index + 1); });
			item.querySelector('.pwq-remove').addEventListener('click', function () {
				if (photo.isNew) URL.revokeObjectURL(photo.url);
				photos.splice(index, 1);
				renderPhotos();
			});
			bindPointerDrag(item, item.querySelector('.pwq-handle'), index);
			photoList.appendChild(item);
		});
		if (previousRects && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
			Array.prototype.forEach.call(photoList.children, function (item) {
				var previous = previousRects[item.dataset.key];
				if (!previous) return;
				var current = item.getBoundingClientRect();
				var dx = previous.left - current.left;
				var dy = previous.top - current.top;
				if ((dx || dy) && item.animate) item.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'translate(0,0)' }], { duration: 220, easing: 'cubic-bezier(.2,.8,.2,1)' });
			});
			var settled = Array.prototype.find.call(photoList.children, function (item) { return item.dataset.key === settledKey; });
			if (settled) { settled.classList.add('is-settling'); window.setTimeout(function () { settled.classList.remove('is-settling'); }, 240); }
		}
		photoSummary.textContent = photos.length ? config.i18n.photos.replace('%d', photos.length) : '';
	}

	function bindPointerDrag(item, handle, index) {
		handle.addEventListener('pointerdown', function (event) {
			if (event.button !== undefined && event.button !== 0) return;
			event.preventDefault();
			handle.setPointerCapture(event.pointerId);
			drag = { pointerId: event.pointerId, from: index, over: index, item: item, startX: event.clientX, startY: event.clientY };
			item.classList.add('is-dragging');
			item.style.pointerEvents = 'none';
		});
		handle.addEventListener('pointermove', function (event) {
			if (!drag || drag.pointerId !== event.pointerId) return;
			event.preventDefault();
			var dx = event.clientX - drag.startX;
			var dy = event.clientY - drag.startY;
			drag.item.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(1.045) rotate(' + Math.max(-2, Math.min(2, dx / 80)) + 'deg)';
			var target = document.elementFromPoint(event.clientX, event.clientY);
			var over = target && target.closest('.pwq-photo');
			if (over && photoList.contains(over)) {
				drag.over = parseInt(over.dataset.index, 10);
				Array.prototype.forEach.call(photoList.children, function (child) { child.classList.toggle('is-drop-target', child === over); });
			}
			var edge = 64;
			if (event.clientY < edge) window.scrollBy(0, -14);
			if (event.clientY > window.innerHeight - edge) window.scrollBy(0, 14);
		});
		function finish(event) {
			if (!drag || drag.pointerId !== event.pointerId) return;
			var from = drag.from;
			var to = drag.over;
			drag.item.classList.remove('is-dragging');
			drag.item.style.pointerEvents = '';
			drag.item.style.transform = '';
			Array.prototype.forEach.call(photoList.children, function (child) { child.classList.remove('is-drop-target'); });
			drag = null;
			if (from !== to && navigator.vibrate) navigator.vibrate(12);
			movePhoto(from, to);
		}
		handle.addEventListener('pointerup', finish);
		handle.addEventListener('pointercancel', finish);
	}

	function filterCategories() {
		var term = categorySearch.value.trim().toLocaleLowerCase();
		var selected = categorySelect.value;
		categorySelect.innerHTML = '';
		categoryOptions.forEach(function (option, index) {
			if (index === 0 || option.label.toLocaleLowerCase().indexOf(term) !== -1) {
				var node = document.createElement('option');
				node.value = option.value;
				node.textContent = option.label;
				categorySelect.appendChild(node);
			}
		});
		if (Array.prototype.some.call(categorySelect.options, function (option) { return option.value === selected; })) categorySelect.value = selected;
	}

	function toggleStockQuantity() {
		var managed = stockMode.value === 'managed';
		stockQuantity.hidden = !managed;
		stockQuantityInput.required = managed;
	}

	function deleteProduct() {
		var id = Number(form.elements.product_id.value || 0);
		var name = form.elements.product_name.value.trim();
		if (!id || !window.confirm(config.i18n.confirmDelete.replace('%s', name))) return;
		deleteButton.disabled = true;
		message.textContent = config.i18n.deleting;
		request('delete', { product_id: id }).then(function (data) {
			message.textContent = data.message;
			setTimeout(function () { releasePhotos(); showEditor(false); loadProducts(true); }, 500);
		}).catch(function (error) {
			message.textContent = error.message;
			deleteButton.disabled = false;
		});
	}

	function submitForm(event) {
		event.preventDefault();
		if (!form.reportValidity()) return;
		if (!photos.length) { message.textContent = config.i18n.photoRequired; return; }
		var button = form.querySelector('button[type="submit"]');
		button.disabled = true;
		message.textContent = config.i18n.preparingPhotos;
		Promise.all(photos.map(function (photo) {
			return photo.isNew ? preparePhoto(photo.file) : Promise.resolve(null);
		})).then(function (prepared) {
			var payload = new FormData(form);
			payload.delete('new_images');
			photos.forEach(function (photo, index) {
				payload.append('image_order[]', photo.key);
				if (photo.isNew) payload.append('new_images[' + photo.localId + ']', prepared[index].blob, prepared[index].name);
				else payload.append('existing_images[]', photo.id);
			});
			message.textContent = config.i18n.saving;
			return request('save', payload, true);
		}).then(function (data) {
			message.textContent = data.message;
			setTimeout(function () { showEditor(false); loadProducts(true); }, 500);
		}).catch(function (error) { message.textContent = error.message; }).finally(function () { button.disabled = false; });
	}

	if (qr && window.jQuery && window.jQuery.fn && window.jQuery.fn.qrcode) {
		window.jQuery(qr).empty().qrcode({ text: qr.dataset.url, width: 184, height: 184 });
	}
	newButton.addEventListener('click', openNew);
	if (disconnectButton) {
		disconnectButton.addEventListener('click', function () {
			if (!window.confirm(config.i18n.confirmDisconnect)) return;
			disconnectButton.disabled = true;
			request('disconnect', {}).then(function (data) {
				window.alert(data.message);
				window.location.reload();
			}).catch(function (error) {
				window.alert(error.message);
				disconnectButton.disabled = false;
			});
		});
	}
	window.addEventListener('beforeinstallprompt', function (event) {
		event.preventDefault();
		installPrompt = event;
		if (installButton && !isStandalone() && detectPlatform() === 'android') installButton.hidden = false;
	});
	if (installButton) {
		installButton.hidden = isStandalone() || detectPlatform() !== 'android';
		installButton.addEventListener('click', function () {
			if (!installPrompt) {
				if (installFallback) installFallback.hidden = false;
				installButton.setAttribute('aria-expanded', 'true');
				return;
			}
			installPrompt.prompt();
			installPrompt.userChoice.then(function (choice) {
				installPrompt = null;
				if (!choice || choice.outcome !== 'accepted') {
					if (installFallback) installFallback.hidden = false;
					installButton.setAttribute('aria-expanded', 'true');
				}
			});
		});
	}
	window.addEventListener('appinstalled', function () {
		if (installButton) installButton.hidden = true;
		if (installFallback) installFallback.hidden = true;
	});
	backButton.addEventListener('click', function () { releasePhotos(); showEditor(false); });
	imageInput.addEventListener('change', function () { addFiles(imageInput.files); });
	categorySearch.addEventListener('input', filterCategories);
	stockMode.addEventListener('change', toggleStockQuantity);
	deleteButton.addEventListener('click', deleteProduct);
	form.addEventListener('submit', submitForm);
	Array.prototype.forEach.call(form.elements.publish_mode, function (radio) {
		radio.addEventListener('change', function () { var active = radio.checked && radio.value === 'future'; schedule.hidden = !active; scheduleInput.required = active; });
	});
	schedule.hidden = !form.querySelector('[name="publish_mode"][value="future"]').checked;
	search.addEventListener('input', function () { clearTimeout(debounceTimer); debounceTimer = setTimeout(function () { loadProducts(true); }, 250); });
	statusFilter.addEventListener('change', function () { loadProducts(true); });
	moreButton.addEventListener('click', function () { page += 1; loadProducts(false); });
	if ('serviceWorker' in navigator && config.serviceWorkerUrl) navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: config.serviceWorkerScope }).catch(function () {});
	reportDevice();
	if (pairingPanel) { pollPairStatus(); pairPoll = window.setInterval(pollPairStatus, 2000); }
	loadProducts(true);
}());

