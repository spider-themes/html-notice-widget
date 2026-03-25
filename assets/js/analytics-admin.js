/**
 * HTML Notice Widget — Analytics Admin JavaScript
 *
 * Handles product/campaign selection, AJAX data loading, and detail panel.
 *
 * @package HTML_Notice_Widget
 */

/* global jQuery, hnwAnalytics */

(function ($) {
	'use strict';

	/* ==========================================================================
	   State
	   ========================================================================== */
	let currentEndpoint = '';

	/* ==========================================================================
	   Helpers
	   ========================================================================== */

	/**
	 * Make an authenticated REST API request
	 *
	 * @param {string} path REST path (relative to namespace).
	 * @return {jQuery.jqXHR}
	 */
	function apiGet(path) {
		return $.ajax({
			url: hnwAnalytics.restUrl + '/' + path,
			method: 'GET',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', hnwAnalytics.nonce);
			},
		});
	}

	/**
	 * Format a number with commas
	 *
	 * @param {number} num Number to format.
	 * @return {string}
	 */
	function formatNumber(num) {
		return parseInt(num, 10).toLocaleString();
	}

	/* ==========================================================================
	   Campaign List Rendering
	   ========================================================================== */

	/**
	 * Load and render campaign list for a product
	 *
	 * @param {string} endpoint Product endpoint slug.
	 */
	function loadProductCampaigns(endpoint) {
		currentEndpoint = endpoint;

		var $list = $('#hnw-a-campaign-list');
		$list.html(
			'<div class="hnw-analytics-loading">' +
			'<div class="hnw-skeleton hnw-skeleton--row"></div>' +
			'<div class="hnw-skeleton hnw-skeleton--row"></div>' +
			'<div class="hnw-skeleton hnw-skeleton--row"></div>' +
			'</div>'
		);

		// Hide detail panel.
		$('#hnw-a-detail-panel').hide();

		apiGet('analytics/summary/' + encodeURIComponent(endpoint))
			.done(function (data) {
				if (!data.campaigns || data.campaigns.length === 0) {
					$list.html(
						'<div class="hnw-empty" style="padding:30px;">' +
						'<div class="hnw-empty__title" style="font-size:15px;">No campaigns found</div>' +
						'<p class="hnw-empty__text" style="margin:6px 0 0;">This product has no campaigns yet, or no analytics data has been recorded.</p>' +
						'</div>'
					);
					return;
				}

				var html = '<table class="hnw-analytics-table">';
				html += '<thead><tr>';
				html += '<th>Campaign</th><th>Status</th><th>Impressions</th><th>Clicks</th><th>Dismissals</th><th>CTR</th><th>Sites</th>';
				html += '</tr></thead><tbody>';

				$.each(data.campaigns, function (i, c) {
					var statusCls = c.enabled ? 'hnw-badge--enabled' : 'hnw-badge--disabled';
					var statusText = c.enabled ? 'Active' : 'Inactive';
					var ctr = c.stats.ctr || 0;

					html += '<tr class="hnw-campaign-row" data-campaign-id="' + c.campaign_id + '" data-title="' + $('<span>').text(c.title).html() + '">';
					html += '<td class="hnw-campaign-row__name">' + $('<span>').text(c.title).html() + '</td>';
					html += '<td><span class="hnw-badge ' + statusCls + '">' + statusText + '</span></td>';
					html += '<td>' + formatNumber(c.stats.impressions) + '</td>';
					html += '<td>' + formatNumber(c.stats.clicks) + '</td>';
					html += '<td>' + formatNumber(c.stats.dismissals) + '</td>';
					html += '<td>' + ctr + '%</td>';
					html += '<td>' + formatNumber(c.stats.unique_sites) + '</td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
				$list.html(html);
			})
			.fail(function () {
				$list.html(
					'<div class="hnw-empty" style="padding:30px;">' +
					'<div class="hnw-empty__title" style="font-size:15px;">Failed to load data</div>' +
					'<p class="hnw-empty__text" style="margin:6px 0 0;">Please try again.</p>' +
					'</div>'
				);
			});
	}

	/* ==========================================================================
	   Campaign Detail
	   ========================================================================== */

	/**
	 * Load and show detail panel for a campaign
	 *
	 * @param {string} campaignId Campaign UUID.
	 * @param {string} title      Campaign title.
	 */
	function loadCampaignDetail(campaignId, title) {
		var $panel = $('#hnw-a-detail-panel');
		$('#hnw-a-detail-title').text(title);
		$panel.show();

		// Reset values to loading.
		$('#hnw-a-d-impressions, #hnw-a-d-clicks, #hnw-a-d-dismissals, #hnw-a-d-sites').text('…');
		$('#hnw-a-d-ctr').text('…');
		$('#hnw-a-d-ctr-bar').css('width', '0%');

		apiGet('analytics/campaign/' + encodeURIComponent(currentEndpoint) + '/' + encodeURIComponent(campaignId))
			.done(function (data) {
				var s = data.stats;
				$('#hnw-a-d-impressions').text(formatNumber(s.impressions));
				$('#hnw-a-d-clicks').text(formatNumber(s.clicks));
				$('#hnw-a-d-dismissals').text(formatNumber(s.dismissals));
				$('#hnw-a-d-sites').text(formatNumber(s.unique_sites));
				$('#hnw-a-d-ctr').text(s.ctr + '%');
				$('#hnw-a-d-ctr-bar').css('width', Math.min(s.ctr, 100) + '%');
			})
			.fail(function () {
				$('#hnw-a-d-impressions, #hnw-a-d-clicks, #hnw-a-d-dismissals, #hnw-a-d-sites').text('—');
				$('#hnw-a-d-ctr').text('—');
			});
	}

	/* ==========================================================================
	   Boot — attach handlers on DOMReady
	   ========================================================================== */
	$(function () {
		// Auto-load first product if exists.
		if (hnwAnalytics.products && hnwAnalytics.products.length > 0) {
			loadProductCampaigns(hnwAnalytics.products[0].endpoint);
		}

		// ── Product sidebar click ──
		$(document).on('click', '.hnw-sidebar-item', function () {
			var $item = $(this);
			$('.hnw-sidebar-item').removeClass('hnw-sidebar-item--active');
			$item.addClass('hnw-sidebar-item--active');

			var endpoint = $item.data('endpoint');
			var product = $item.data('product');
			$('#hnw-a-product-title').text(product + ' — Campaigns');
			loadProductCampaigns(endpoint);
		});

		// ── Campaign row click ──
		$(document).on('click', '.hnw-campaign-row', function () {
			var $row = $(this);
			$('.hnw-campaign-row').removeClass('hnw-campaign-row--active');
			$row.addClass('hnw-campaign-row--active');

			loadCampaignDetail($row.data('campaign-id'), $row.data('title'));
		});

		// ── Close detail panel ──
		$(document).on('click', '#hnw-a-detail-close', function (e) {
			e.preventDefault();
			$('#hnw-a-detail-panel').slideUp(200);
			$('.hnw-campaign-row').removeClass('hnw-campaign-row--active');
		});
	});
})(jQuery);
