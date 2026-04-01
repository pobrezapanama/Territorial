/**
 * PSP Territorial Admin JS
 *
 * Handles admin panel interactions.
 */
(function ($) {
	'use strict';

	var PSPTerritorial = {

		init: function () {
			this.bindEvents();
			this.initTypeSelector();
		},

		bindEvents: function () {
			// Confirm delete with children warning.
			$(document).on('click', '.psp-confirm-delete', function (e) {
				var children = parseInt($(this).data('children'), 10);
				var msg = pspTerritorial.i18n.confirmDelete;

				if (children > 0) {
					msg = '⚠️ Este territorio tiene ' + children + ' elemento(s) hijo(s).\n\n' + msg;
				}

				if (!confirm(msg)) {
					e.preventDefault();
					return false;
				}
			});

			// Auto-generate slug from name.
			$('#name').on('input', function () {
				var $slug = $('#slug');
				if ($slug.val() === '' || $slug.data('auto')) {
					$slug.val(PSPTerritorial.slugify($(this).val())).data('auto', true);
				}
			});

			$('#slug').on('input', function () {
				$(this).data('auto', false);
			});
		},

		/**
		 * Handle the type selector: show/hide parent field and reload parent options.
		 */
		initTypeSelector: function () {
			var $typeSelect = $('[data-psp-type-selector]');
			if (!$typeSelect.length) return;

			$typeSelect.on('change', function () {
				PSPTerritorial.onTypeChange($(this).val());
			});
		},

		onTypeChange: function (type) {
			var $parentRow = $('#row-parent-id');
			var $parentSelect = $('#parent_id');

			if (type === 'province') {
				$parentRow.hide();
				$parentSelect.val('');
				return;
			}

			$parentRow.show();

			// Determine parent type from hierarchy.
			var parentTypes = {
				district: 'province',
				corregimiento: 'district',
				community: 'corregimiento'
			};
			var parentType = parentTypes[type];
			if (!parentType) return;

			// Load parent options via REST API.
			$parentSelect.html('<option>' + pspTerritorial.i18n.loading + '</option>');

			$.ajax({
				url: pspTerritorial.restUrl + '/territories',
				data: { type: parentType, limit: 2000 },
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', pspTerritorial.restNonce);
				},
				success: function (response) {
					if (!response.success || !response.data) return;

					var options = '<option value="">' + '— Seleccionar padre —' + '</option>';
					$.each(response.data, function (i, item) {
						options += '<option value="' + item.id + '">' + item.name + '</option>';
					});
					$parentSelect.html(options);
				},
				error: function () {
					$parentSelect.html('<option>' + pspTerritorial.i18n.error + '</option>');
				}
			});
		},

		/**
		 * Convert a string to a URL-friendly slug.
		 *
		 * @param {string} str Input string.
		 * @returns {string}
		 */
		slugify: function (str) {
			return str
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '')
				.replace(/[^a-z0-9\s-]/g, '')
				.trim()
				.replace(/[\s_-]+/g, '-')
				.replace(/^-+|-+$/g, '');
		}
	};

	$(document).ready(function () {
		PSPTerritorial.init();
	});

}(jQuery));
