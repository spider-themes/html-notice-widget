/**
 * HTML Notice Widget — Analytics Admin JavaScript
 *
 * Handles product/campaign selection, AJAX data loading, and detail panel.
 * All API responses are cached in-memory so switching between products
 * and campaigns is instant after the first load.
 *
 * @package HTML_Notice_Widget
 */

/* global jQuery, hnwAnalytics */

(function ($) {
	'use strict';

	/* ==========================================================================
	   State & Caches
	   ========================================================================== */
	let currentEndpoint = '';

	/**
	 * Product summary cache keyed by endpoint.
	 * Once a product's campaign list is fetched, subsequent sidebar clicks
	 * render instantly from this cache — no API call.
	 *
	 * @type {Object<string, Object>}
	 */
	const productCache = {};

	/**
	 * Per-campaign stats cache keyed by campaign_id.
	 * Populated from the product summary response.
	 *
	 * @type {Object<string, Object>}
	 */
	let campaignCache = {};

	/**
	 * Daily breakdown cache keyed by "endpoint:campaign_id".
	 * Once fetched, clicking the same campaign row is instant.
	 *
	 * @type {Object<string, Array>}
	 */
	const dailyCache = {};

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

	/**
	 * Tooltip definitions for table headers
	 */
	var headerTooltips = {
		'Campaign':    'The title of the campaign notice',
		'Status':      'Whether the campaign is currently enabled or disabled',
		'Impressions': 'Total times this notice was displayed on remote sites',
		'Clicks':      'Total link clicks inside the notice content',
		'Dismissals':  'Total times users closed this notice',
		'CTR':         'Click-Through Rate: percentage of impressions that resulted in a click',
		'Sites':       'Number of unique sites where this notice appeared',
	};

	/**
	 * Build an inline CTR progress bar HTML
	 *
	 * @param {number} ctr CTR percentage value.
	 * @return {string} HTML string for the progress bar.
	 */
	function ctrBarHtml(ctr) {
		var width = Math.min(ctr, 100);
		return '<div class="hnw-ctr-inline">' +
			'<div class="hnw-ctr-inline__bar">' +
			'<div class="hnw-ctr-inline__fill" style="width:' + width + '%"></div>' +
			'</div>' +
			'<span class="hnw-ctr-inline__value">' + ctr + '%</span>' +
			'</div>';
	}

	/**
	 * Render a skeleton loading placeholder
	 *
	 * @param {number} rows Number of skeleton rows.
	 * @return {string} HTML string.
	 */
	function skeletonHtml(rows) {
		var html = '<div class="hnw-analytics-loading">';
		for (var i = 0; i < rows; i++) {
			html += '<div class="hnw-skeleton hnw-skeleton--row"></div>';
		}
		html += '</div>';
		return html;
	}

	/* ==========================================================================
	   Campaign List Rendering
	   ========================================================================== */

	/**
	 * Render campaign list HTML from a data object
	 *
	 * @param {Object} data API response data with campaigns array.
	 * @return {string} Table HTML.
	 */
	function renderCampaignTable(data) {
		if (!data.campaigns || data.campaigns.length === 0) {
			return '<div class="hnw-empty" style="padding:30px;">' +
				'<div class="hnw-empty__title" style="font-size:15px;">No campaigns found</div>' +
				'<p class="hnw-empty__text" style="margin:6px 0 0;">This product has no campaigns yet, or no analytics data has been recorded.</p>' +
				'</div>';
		}

		var html = '<table class="hnw-analytics-table">';
		html += '<thead><tr>';

		var headers = ['Campaign', 'Status', 'Impressions', 'Clicks', 'Dismissals', 'CTR', 'Sites'];
		$.each(headers, function (i, h) {
			var tip = headerTooltips[h] || '';
			html += '<th>';
			html += h;
			if (tip) {
				html += ' <span class="hnw-help-icon" data-hnw-tooltip="' + tip + '" data-tooltip-pos="bottom">?</span>';
			}
			html += '</th>';
		});

		html += '</tr></thead><tbody>';

		$.each(data.campaigns, function (i, c) {
			var statusCls = c.enabled ? 'hnw-badge--enabled' : 'hnw-badge--disabled';
			var statusText = c.enabled ? 'Active' : 'Inactive';
			var ctr = c.stats.ctr || 0;

			// Cache per-campaign stats.
			campaignCache[c.campaign_id] = {
				title: c.title,
				impressions: c.stats.impressions,
				clicks: c.stats.clicks,
				dismissals: c.stats.dismissals,
				unique_sites: c.stats.unique_sites,
				ctr: ctr,
			};

			html += '<tr class="hnw-campaign-row" data-campaign-id="' + c.campaign_id + '" data-title="' + $('<span>').text(c.title).html() + '">';
			html += '<td class="hnw-campaign-row__name">' + $('<span>').text(c.title).html() + '</td>';
			html += '<td><span class="hnw-badge ' + statusCls + '">' + statusText + '</span></td>';
			html += '<td>' + formatNumber(c.stats.impressions) + '</td>';
			html += '<td>' + formatNumber(c.stats.clicks) + '</td>';
			html += '<td>' + formatNumber(c.stats.dismissals) + '</td>';
			html += '<td>' + ctrBarHtml(ctr) + '</td>';
			html += '<td>' + formatNumber(c.stats.unique_sites) + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		return html;
	}

	/**
	 * Load and render campaign list for a product.
	 * Uses productCache to avoid redundant API calls.
	 *
	 * @param {string} endpoint Product endpoint slug.
	 */
	function loadProductCampaigns(endpoint) {
		currentEndpoint = endpoint;
		campaignCache = {};

		var $list = $('#hnw-a-campaign-list');

		// Hide detail panel when switching products.
		$('#hnw-a-detail-panel').hide();
		$('.hnw-campaign-row').removeClass('hnw-campaign-row--active');

		// Check cache first — render instantly if available.
		if (productCache[endpoint]) {
			$list.html(renderCampaignTable(productCache[endpoint]));

			// Re-populate campaignCache from stored data.
			$.each(productCache[endpoint].campaigns || [], function (i, c) {
				campaignCache[c.campaign_id] = {
					title: c.title,
					impressions: c.stats.impressions,
					clicks: c.stats.clicks,
					dismissals: c.stats.dismissals,
					unique_sites: c.stats.unique_sites,
					ctr: c.stats.ctr || 0,
				};
			});
			return;
		}

		// Show skeleton loader.
		$list.html(skeletonHtml(4));

		apiGet('analytics/summary/' + encodeURIComponent(endpoint))
			.done(function (data) {
				// Store in product cache.
				productCache[endpoint] = data;

				$list.html(renderCampaignTable(data));
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
	   Campaign Detail — Daily Breakdown Only
	   ========================================================================== */

	/**
	 * Render daily breakdown table HTML
	 *
	 * @param {Array} daily Array of daily stat rows.
	 * @return {string} Table HTML.
	 */
	function renderDailyTable(daily) {
		if (!daily || daily.length === 0) {
			return '<p class="hnw-daily-empty">No daily data recorded yet.</p>';
		}

		var html = '<table class="hnw-daily-breakdown">';
		html += '<thead><tr><th>Date</th><th>Impressions</th><th>Clicks</th><th>Dismissals</th><th>CTR</th><th>Sites</th></tr></thead>';
		html += '<tbody>';

		$.each(daily, function (i, d) {
			html += '<tr>';
			html += '<td>' + d.date + '</td>';
			html += '<td>' + formatNumber(d.impressions) + '</td>';
			html += '<td>' + formatNumber(d.clicks) + '</td>';
			html += '<td>' + formatNumber(d.dismissals) + '</td>';
			html += '<td>' + ctrBarHtml(d.ctr) + '</td>';
			html += '<td>' + formatNumber(d.unique_sites) + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		return html;
	}

	/**
	 * Show detail panel with daily breakdown for a campaign.
	 * Uses dailyCache to avoid redundant API calls.
	 *
	 * @param {string} campaignId Campaign UUID.
	 * @param {string} title      Campaign title.
	 */
	function showCampaignDetail(campaignId, title) {
		var $panel = $('#hnw-a-detail-panel');
		$('#hnw-a-detail-title').text(title);
		$panel.show();

		var $daily = $('#hnw-a-daily-table');
		var cacheKey = currentEndpoint + ':' + campaignId;

		// Check daily cache — render instantly if available.
		if (dailyCache[cacheKey]) {
			$daily.html(renderDailyTable(dailyCache[cacheKey]));
			return;
		}

		// Show skeleton loader.
		$daily.html(skeletonHtml(3));

		apiGet('analytics/campaign/' + encodeURIComponent(currentEndpoint) + '/' + encodeURIComponent(campaignId))
			.done(function (data) {
				// Store in daily cache.
				dailyCache[cacheKey] = data.daily || [];

				$daily.html(renderDailyTable(data.daily));
			})
			.fail(function () {
				$daily.html('<p class="hnw-daily-empty">Failed to load daily data.</p>');
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

			showCampaignDetail($row.data('campaign-id'), $row.data('title'));
		});

		// ── Close detail panel ──
		$(document).on('click', '#hnw-a-detail-close', function (e) {
			e.preventDefault();
			$('#hnw-a-detail-panel').slideUp(200);
			$('.hnw-campaign-row').removeClass('hnw-campaign-row--active');
		});

		// ── Refresh / Rollup button ──
		$(document).on('click', '#hnw-a-refresh-btn', function () {
			var $btn = $(this);
			if ($btn.hasClass('hnw-is-loading')) {
				return;
			}

			var originalHtml = $btn.html();
			$btn.addClass('hnw-is-loading').html('<span class="hnw-spin">&#8635;</span> Running…');

			$.post(hnwAnalytics.ajaxUrl, {
				action: 'hnw_trigger_rollup',
				nonce:  hnwAnalytics.rollupNonce
			}).done(function (res) {
				if (res.success) {
					// Clear all caches so fresh data is loaded.
					Object.keys(productCache).forEach(function (k) { delete productCache[k]; });
					Object.keys(campaignCache).forEach(function (k) { delete campaignCache[k]; });
					Object.keys(dailyCache).forEach(function (k) { delete dailyCache[k]; });

					$btn.html('&#10003; Done!');
					// Reload current product.
					if (currentEndpoint) {
						loadProductCampaigns(currentEndpoint);
					}
				} else {
					$btn.html('&#10007; Failed');
				}
			}).fail(function () {
				$btn.html('&#10007; Error');
			}).always(function () {
				setTimeout(function () {
					$btn.removeClass('hnw-is-loading').html(originalHtml);
				}, 1500);
			});
		});
	});
})(jQuery);
