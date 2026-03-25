/**
 * HTML Notice Widget — Admin JavaScript
 *
 * Component-based JS: HNWModal, HNWTooltip, HNWClipboard, HNWFormState, HNWNotice
 *
 * @package HTML_Notice_Widget
 */

/* global jQuery, ajaxurl */

(function ($) {
	'use strict';

	/* ==========================================================================
	   HNWModal — centralised open / close with animation
	   ========================================================================== */
	const HNWModal = {
		/**
		 * Open a modal by its DOM element or selector
		 *
		 * @param {HTMLElement|string} modal Element or CSS selector.
		 */
		open(modal) {
			const el = typeof modal === 'string' ? document.querySelector(modal) : modal;
			if (!el) {
				return;
			}
			el.style.display = 'flex';
			// Force reflow so the transition triggers
			void el.offsetHeight;
			el.classList.add('hnw-modal--open');
			document.body.style.overflow = 'hidden';
		},

		/**
		 * Close a modal and reset its form if present
		 *
		 * @param {HTMLElement|string} modal Element or CSS selector.
		 */
		close(modal) {
			const el = typeof modal === 'string' ? document.querySelector(modal) : modal;
			if (!el) {
				return;
			}
			el.classList.remove('hnw-modal--open');
			setTimeout(function () {
				el.style.display = 'none';
				document.body.style.overflow = '';
				const form = el.querySelector('form');
				if (form) {
					form.reset();
				}
			}, 300);
		},

		/**
		 * Bind all close triggers (×, Cancel, backdrop, Escape)
		 */
		bindCloseEvents() {
			// Close & cancel buttons
			$(document).on('click', '.hnw-modal__close, .hnw-modal-cancel', function (e) {
				e.preventDefault();
				HNWModal.close($(this).closest('.hnw-modal')[0]);
			});

			// Backdrop click
			$(document).on('click', '.hnw-modal__backdrop', function () {
				HNWModal.close($(this).closest('.hnw-modal')[0]);
			});

			// Escape key
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape') {
					$('.hnw-modal--open').each(function () {
						HNWModal.close(this);
					});
				}
			});
		},
	};

	/* ==========================================================================
	   HNWClipboard — copy text with visual feedback
	   ========================================================================== */
	const HNWClipboard = {
		/**
		 * Copy text and toggle button label
		 *
		 * @param {string}      text   Text to copy.
		 * @param {HTMLElement}  btn    Button that triggered the copy.
		 */
		copy(text, btn) {
			if (!navigator.clipboard) {
				return;
			}
			navigator.clipboard.writeText(text).then(function () {
				const $btn = $(btn);
				const original = $btn.html();
				$btn.html('Copied!');
				setTimeout(function () {
					$btn.html(original);
				}, 2000);
			});
		},

		/**
		 * Bind all copy buttons
		 */
		bind() {
			// Code-block copy buttons
			$(document).on('click', '.hnw-code-copy', function (e) {
				e.preventDefault();
				const targetId = $(this).data('target');
				const text = $('#' + targetId).text();
				HNWClipboard.copy(text, this);
			});

			// API URL copy buttons
			$(document).on('click', '.hnw-api-copy', function (e) {
				e.preventDefault();
				const text = $(this).closest('.hnw-api-block').find('.hnw-api-block__url').text().trim();
				HNWClipboard.copy(text, this);
			});
		},
	};

	/* ==========================================================================
	   HNWFormState — populate edit-modals from data-attributes
	   ========================================================================== */
	const HNWFormState = {
		/**
		 * Bind form-submission loading states
		 */
		bindSubmitLoading() {
			$(document).on('submit', '.hnw-modal form', function () {
				const $btn = $(this).find('[type="submit"]');
				$btn.prop('disabled', true);
				if ($btn.is('input')) {
					$btn.val('Processing…');
				} else {
					$btn.text('Processing…');
				}
			});
		},
	};

	/* ==========================================================================
	   HNWNotice — auto-dismiss timed admin notices
	   ========================================================================== */
	const HNWNotice = {
		/**
		 * Auto-dismiss success notices after 6 seconds
		 */
		autoDismiss() {
			$('.hnw-notice--success').each(function () {
				const $notice = $(this);
				setTimeout(function () {
					$notice.animate({ opacity: 0, height: 0, marginBottom: 0, paddingTop: 0, paddingBottom: 0 }, 350, function () {
						$notice.remove();
					});
				}, 6000);
			});
		},
	};

	/* ==========================================================================
	   Boot — attach all handlers on DOMReady
	   ========================================================================== */
	$(function () {
		// ── Modal Close Events ──
		HNWModal.bindCloseEvents();

		// ── Clipboard ──
		HNWClipboard.bind();

		// ── Form Submit Loading ──
		HNWFormState.bindSubmitLoading();

		// ── Auto-dismiss notices ──
		HNWNotice.autoDismiss();

		// ── Add Site Modal ──
		$(document).on('click', '#hnw-add-product-btn, #hnw-add-product-empty', function (e) {
			e.preventDefault();
			HNWModal.open('#hnw-modal-add-product');
		});

		// ── Edit Site Modal ──
		$(document).on('click', '.hnw-edit-site', function (e) {
			e.preventDefault();
			const $el = $(this);
			$('#hnw-edit-site-id').val($el.data('site-id'));
			$('#hnw-edit-product').val($el.data('site-name'));
			HNWModal.open('#hnw-modal-edit-product');
		});

		// ── Add Content Modal ──
		$(document).on('click', '.hnw-add-content', function (e) {
			e.preventDefault();
			$('#hnw-add-content-site-id').val($(this).data('site-id'));
			$('#hnw-add-content-site-name').text($(this).data('site-name'));
			HNWModal.open('#hnw-modal-add-campaign');
		});

		// ── Edit Content Modal ──
		$(document).on('click', '.hnw-edit-content', function (e) {
			e.preventDefault();
			const $el = $(this);
			$('#hnw-edit-content-site-id').val($el.data('site-id'));
			$('#hnw-edit-content-id').val($el.data('content-id'));
			$('#hnw-edit-content-title').val($el.data('content-title'));
			$('#hnw-edit-content-desc').val($el.data('content-description') || '');
			$('#hnw-edit-content-html').val($el.data('content-html'));
			$('#hnw-edit-content-enabled').prop('checked', $el.data('content-enabled') == 1);
			$('#hnw-edit-content-title-display').text($el.data('content-title'));

			// Schedule fields
			var start = $el.data('schedule-start') || '';
			var end 	= $el.data('schedule-end') || '';
			$('#hnw-edit-schedule-start').val(start ? String(start).replace(' ', 'T').substring(0, 16) : '');
			$('#hnw-edit-schedule-end').val(end ? String(end).replace(' ', 'T').substring(0, 16) : '');

			// Targeting fields
			$('#hnw-edit-targeting-pro').val($el.data('targeting-pro') || 'all');
			$('#hnw-edit-targeting-version-op').val($el.data('targeting-version-op') || '');
			$('#hnw-edit-targeting-version').val($el.data('targeting-version') || '');

			// Roles checkboxes
			var rolesStr = String($el.data('targeting-roles') || '');
			var roles = rolesStr ? rolesStr.split(',') : [];
			$('#hnw-edit-targeting-roles input[type="checkbox"]').each(function () {
				$(this).prop('checked', roles.indexOf($(this).val()) !== -1);
			});

			// Auto-expand targeting section if any rule is set
			var hasTargeting = ($el.data('targeting-pro') && $el.data('targeting-pro') !== 'all')
				|| $el.data('targeting-version-op')
				|| rolesStr;
			var $editBody = $('#hnw-modal-edit-campaign .hnw-targeting-body');
			var $editToggle = $('#hnw-modal-edit-campaign .hnw-targeting-toggle');
			if (hasTargeting) {
				$editBody.show();
				$editToggle.addClass('hnw-is-open');
			} else {
				$editBody.hide();
				$editToggle.removeClass('hnw-is-open');
			}

			HNWModal.open('#hnw-modal-edit-campaign');
		});

		// ── Docs Modal ──
		$(document).on('click', '.hnw-user-doc-trigger', function (e) {
			e.preventDefault();
			const product = $(this).data('product');
			const apiUrl = $(this).data('api-url');

			$('#hnw-user-doc-product-name').text(product);

			const initCode =
				"<?php\n" +
				"// Remote Notice Integration\n" +
				"require_once PLUGIN_PATH . 'includes/remote-notices/class-remote-notice-client.php';\n\n" +
				"add_action( 'admin_init', function() {\n" +
				"    if ( class_exists( 'Remote_Notice_Client' ) ) {\n" +
				"        Remote_Notice_Client::init( '" + product + "', [\n" +
				"            'api_url'        => '" + apiUrl + "',\n" +
				"            'plugin_version' => YOUR_PLUGIN_VERSION,\n" +
				"            'is_pro'         => defined( 'YOUR_PLUGIN_PRO' ) && YOUR_PLUGIN_PRO,\n" +
				"        ]);\n" +
				"    }\n" +
				"});";
			$('#hnw-user-doc-init-code').text(initCode);

			const proCode =
				"<?php\n" +
				"// Option A: Completely disable the client when Pro is active\n" +
				"add_action( 'admin_init', function() {\n" +
				"    if ( defined( 'YOUR_PLUGIN_PRO_ACTIVE' ) && YOUR_PLUGIN_PRO_ACTIVE ) {\n" +
				"        Remote_Notice_Client::disable( '" + product + "' );\n" +
				"        return;\n" +
				"    }\n\n" +
				"    Remote_Notice_Client::init( '" + product + "', [\n" +
				"        'api_url'        => '" + apiUrl + "',\n" +
				"        'plugin_version' => YOUR_PLUGIN_VERSION,\n" +
				"    ]);\n" +
				"});\n\n" +
				"// Option B: Let targeting rules filter per-campaign\n" +
				"// Pass 'is_pro' => true so campaigns with audience\n" +
				"// targeting (Free Only / Pro Only) work automatically.\n" +
				"Remote_Notice_Client::init( '" + product + "', [\n" +
				"    'api_url'        => '" + apiUrl + "',\n" +
				"    'plugin_version' => YOUR_PLUGIN_VERSION,\n" +
				"    'is_pro'         => true,\n" +
				"]);";
			$('#hnw-user-doc-pro-code').text(proCode);

			HNWModal.open('#hnw-modal-user-doc');
		});

		// ── SDK Download ──
		$(document).on('click', '#hnw-download-sdk', function (e) {
			e.preventDefault();
			window.location.href = ajaxurl + '?action=hnw_download_sdk';
		});

		// ── Targeting Section Toggle ──
		$(document).on('click', '.hnw-targeting-toggle', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $body = $btn.next('.hnw-targeting-body');
			$btn.toggleClass('hnw-is-open');
			$body.slideToggle(200);
		});
	});
})(jQuery);
