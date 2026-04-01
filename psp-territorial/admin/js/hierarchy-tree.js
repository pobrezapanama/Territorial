/**
 * PSP Territorial – Hierarchy Tree JS
 *
 * Lazy-loads child territories from the REST API on node expansion.
 */
(function ($) {
	'use strict';

	var PSPTree = {

		restUrl: '',

		init: function () {
			var $tree = $('#psp-hierarchy-tree');
			if (!$tree.length) return;

			this.restUrl = $tree.data('rest-url') || pspTerritorial.restUrl;

			this.bindEvents();
			this.bindSearch();
			this.bindExpandCollapse();
		},

		bindEvents: function () {
			$(document).on('click', '.psp-tree-toggle', function () {
				var $toggle = $(this);
				var $node   = $toggle.closest('.psp-tree-node');
				var $children = $node.children('.psp-tree-children');

				if ($toggle.hasClass('leaf')) return;

				if ($children.is(':visible')) {
					// Collapse.
					$children.slideUp(150);
					$toggle.removeClass('open').text('▶');
				} else {
					// Expand: load children if not yet loaded.
					if ($children.data('loaded') === false || $children.data('loaded') === 'false') {
						PSPTree.loadChildren($node, $children);
					} else {
						$children.slideDown(150);
						$toggle.addClass('open').text('▼');
					}
				}
			});
		},

		loadChildren: function ($node, $children) {
			var parentId   = $node.data('id');
			var parentType = $node.data('type');
			var $toggle    = $node.children('.psp-tree-toggle');

			$children.html('<li class="psp-tree-loading">' + pspTerritorial.i18n.loading + '</li>').show();

			var childTypes = {
				province: 'district',
				district: 'corregimiento',
				corregimiento: 'community'
			};
			var childType = childTypes[parentType];

			$.ajax({
				url: PSPTree.restUrl + '/territories',
				data: { parent_id: parentId, limit: 2000 },
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', pspTerritorial.restNonce);
				},
				success: function (response) {
					$children.empty();

					if (!response.success || !response.data || response.data.length === 0) {
						$children.html('<li class="psp-tree-loading">— sin elementos —</li>');
						$toggle.addClass('leaf').text('·');
						return;
					}

					$.each(response.data, function (i, item) {
						var isLeaf = item.type === 'community';
						var toggleHtml = isLeaf
							? '<span class="psp-tree-toggle leaf">·</span>'
							: '<span class="psp-tree-toggle">▶</span>';

						var editUrl = pspTerritorial.ajaxUrl.replace('admin-ajax.php', 'admin.php')
							+ '?page=psp-territorial&action=edit&id=' + item.id;
						var addUrl = pspTerritorial.ajaxUrl.replace('admin-ajax.php', 'admin.php')
							+ '?page=psp-territorial-add&type=' + (item.type !== 'community' ? childTypes[item.type] : '') + '&parent_id=' + item.id;

						var actionsHtml = '<span class="psp-tree-actions">'
							+ '<a href="' + editUrl + '" class="button button-small">' + 'Editar' + '</a>';

						if (!isLeaf) {
							actionsHtml += ' <a href="' + addUrl + '" class="button button-small">+</a>';
						}
						actionsHtml += '</span>';

						var $li = $('<li/>', {
							'class': 'psp-tree-node psp-tree-' + item.type,
							'data-id': item.id,
							'data-type': item.type
						}).html(
							toggleHtml
							+ '<span class="psp-tree-label">'
							+ '<span class="psp-type-badge psp-type-' + item.type + '">' + item.type_label + '</span>'
							+ ' ' + PSPTree.escapeHtml(item.name)
							+ '</span>'
							+ actionsHtml
							+ (isLeaf ? '' : '<ul class="psp-tree-children" style="display:none" data-loaded="false"></ul>')
						);

						$children.append($li);
					});

					$children.data('loaded', true);
					$toggle.addClass('open').text('▼');
				},
				error: function () {
					$children.html('<li class="psp-tree-loading">' + pspTerritorial.i18n.error + '</li>');
				}
			});
		},

		bindSearch: function () {
			$('#psp-tree-search').on('input', function () {
				var term = $(this).val().toLowerCase().trim();

				if (!term) {
					$('.psp-tree-node').removeClass('psp-tree-hidden');
					$('.psp-tree-highlight').each(function () {
						$(this).replaceWith(document.createTextNode($(this).text()));
					});
					return;
				}

				$('.psp-tree-node').each(function () {
					var $node  = $(this);
					var $label = $node.children('.psp-tree-label');
					var text   = $label.text().toLowerCase();

					if (text.indexOf(term) !== -1) {
						$node.removeClass('psp-tree-hidden');
						$node.parentsUntil('.psp-hierarchy-tree', 'li').removeClass('psp-tree-hidden').show();
					} else {
						$node.addClass('psp-tree-hidden');
					}
				});
			});
		},

		bindExpandCollapse: function () {
			$('#psp-expand-all').on('click', function () {
				$('.psp-tree-children').not('[data-loaded="true"]').each(function () {
					var $children = $(this);
					var $node = $children.closest('.psp-tree-node');
					if ($node.data('type') !== 'community') {
						PSPTree.loadChildren($node, $children);
					}
				});
				$('.psp-tree-children[data-loaded="true"]').slideDown(100);
				$('.psp-tree-toggle').not('.leaf').addClass('open').text('▼');
			});

			$('#psp-collapse-all').on('click', function () {
				$('.psp-tree-children').slideUp(100);
				$('.psp-tree-toggle').not('.leaf').removeClass('open').text('▶');
			});
		},

		escapeHtml: function (str) {
			return str
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}
	};

	$(document).ready(function () {
		PSPTree.init();
	});

}(jQuery));
