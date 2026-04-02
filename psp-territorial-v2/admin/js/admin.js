/* global pspv2, jQuery */
(function ($) {
	'use strict';

	$(function () {

		// ── Import ─────────────────────────────────────────────────
		$('#pspv2-import-btn').on('click', function () {
			var $btn      = $(this);
			var $progress = $('#pspv2-import-progress');
			var $result   = $('#pspv2-import-result');
			var truncate  = $('#pspv2-truncate-check').is(':checked') ? '1' : '0';

			$btn.prop('disabled', true);
			$result.hide().removeClass('notice-success notice-error');
			$progress.show();
			$('#pspv2-import-status').text(pspv2.i18n.importing);

			$.post(pspv2.ajaxUrl, {
				action:   'pspv2_import',
				nonce:    pspv2.nonce,
				truncate: truncate
			})
			.done(function (response) {
				$progress.hide();
				$btn.prop('disabled', false);

				if (response.success) {
					var data = response.data;
					$result
						.addClass('notice-success')
						.html(
							'<p>✅ ' + data.message + '</p>' +
							'<p>Insertados: <strong>' + data.inserted + '</strong> · ' +
							'Omitidos: <strong>' + data.skipped + '</strong> · ' +
							'Errores: <strong>' + data.errors + '</strong></p>'
						)
						.show();
				} else {
					$result
						.addClass('notice-error')
						.html('<p>❌ ' + (response.data ? response.data.message : pspv2.i18n.error) + '</p>')
						.show();
				}
			})
			.fail(function () {
				$progress.hide();
				$btn.prop('disabled', false);
				$result
					.addClass('notice-error')
					.html('<p>❌ ' + pspv2.i18n.error + '</p>')
					.show();
			});
		});

		// ── Export ──────────────────────────────────────────────────
		$('#pspv2-export-btn').on('click', function () {
			// Trigger download via hidden form.
			var $form = $('<form>', {
				method: 'post',
				action: pspv2.ajaxUrl
			});
			$form.append($('<input>', { type: 'hidden', name: 'action', value: 'pspv2_export_json' }));
			$form.append($('<input>', { type: 'hidden', name: 'nonce',  value: pspv2.nonce }));
			$('body').append($form);
			$form.submit().remove();
		});

	});

}(jQuery));
