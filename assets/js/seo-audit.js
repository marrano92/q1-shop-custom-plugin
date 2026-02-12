(function($) {
	'use strict';

	var Q1SeoAudit = {

		init: function() {
			if (q1SeoAudit.context === 'metabox') {
				this.initMetabox();
			} else if (q1SeoAudit.context === 'page') {
				this.initPage();
			}
		},

		initMetabox: function() {
			$('#q1-seo-analyze-btn').on('click', this.doMetaboxAnalyze.bind(this));
		},

		doMetaboxAnalyze: function() {
			var $btn = $('#q1-seo-analyze-btn');
			var $loading = $('#q1-seo-metabox-loading');
			var $error = $('#q1-seo-metabox-error');

			$btn.prop('disabled', true).hide();
			$loading.show();
			$error.hide();

			$.ajax({
				url: q1SeoAudit.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_audit_analyze',
					nonce: q1SeoAudit.nonce,
					post_id: q1SeoAudit.postId
				},
				success: function(response) {
					$loading.hide();
					$btn.prop('disabled', false).show().text(q1SeoAudit.strings.reanalyze);
					if (response.success) {
						this.renderReport(response.data, 'metabox');
					} else {
						$error.show().find('p').text(response.data.message || q1SeoAudit.strings.error);
					}
				}.bind(this),
				error: function(xhr, status, error) {
					$loading.hide();
					$btn.prop('disabled', false).show();
					$error.show().find('p').text(q1SeoAudit.strings.error + ': ' + error);
				},
				timeout: 150000
			});
		},

		initPage: function() {
			var self = this;
			var searchTimeout;

			this.initRecentAuditsAccordion();

			$('#q1-audit-post-search').on('input', function() {
				var query = $(this).val().trim();
				if (query.length < 2) {
					return;
				}

				clearTimeout(searchTimeout);
				searchTimeout = setTimeout(function() {
					$.ajax({
						url: q1SeoAudit.ajaxUrl,
						type: 'POST',
						data: {
							action: 'q1_seo_audit_search_posts',
							nonce: q1SeoAudit.nonce,
							query: query
						},
						success: function(response) {
							if (response.success) {
								var $select = $('#q1-audit-post-select');
								$select.empty().append('<option value="">' + (q1SeoAudit.strings.selectPost || '-- Seleziona --') + '</option>');
								response.data.posts.forEach(function(p) {
									$select.append('<option value="' + parseInt(p.id, 10) + '">' + self.escapeHtml(p.title + ' (' + p.type + ')') + '</option>');
								});
							}
						}
					});
				}, 300);
			});

			$('#q1-audit-post-select').on('change', function() {
				$('#q1-audit-analyze-btn').prop('disabled', !$(this).val());
			});

			$('#q1-audit-analyze-btn').on('click', function() {
				var postId = $('#q1-audit-post-select').val();
				if (!postId) {
					return;
				}

				var $wrapper = $('#q1-seo-page-report-wrapper');
				var $loading = $('#q1-seo-page-loading');
				var $error = $('#q1-seo-page-error');
				var $btn = $(this);

				$wrapper.show();
				$loading.show();
				$error.hide();
				$btn.prop('disabled', true);

				$.ajax({
					url: q1SeoAudit.ajaxUrl,
					type: 'POST',
					data: {
						action: 'q1_seo_audit_analyze',
						nonce: q1SeoAudit.nonce,
						post_id: postId
					},
					success: function(response) {
						$loading.hide();
						$btn.prop('disabled', false);
						if (response.success) {
							self.renderReport(response.data, 'page');
						} else {
							$error.show().find('p').text(response.data.message || q1SeoAudit.strings.error);
						}
					},
					error: function(xhr, status, error) {
						$loading.hide();
						$btn.prop('disabled', false);
						$error.show().find('p').text(q1SeoAudit.strings.error + ': ' + error);
					},
					timeout: 150000
				});
			});

			// Auto-analyze if post_id is preselected via deep link.
			if (q1SeoAudit.postId && $('#q1-audit-post-select').val()) {
				$('#q1-audit-analyze-btn').trigger('click');
			}
		},

		renderReport: function(data, context) {
			context = context || 'metabox';
			var $report = context === 'metabox' ? $('#q1-seo-metabox-report') : $('#q1-seo-page-report');
			$report.empty();

			$report.append(this.renderScore(data.score));
			$report.append(this.renderSummary(data.recommendations));

			if (context === 'page') {
				$report.append(this.renderRecommendationsFull(data.recommendations));
			} else {
				$report.append(this.renderRecommendationsCompact(data.recommendations));
				$report.append('<p><small><a href="' + q1SeoAudit.auditPageUrl + '&post_id=' + q1SeoAudit.postId + '">' + (q1SeoAudit.strings.viewFullReport || 'Vedi report completo') + '</a></small></p>');
			}
		},

		renderScore: function(score) {
			var level = score >= 80 ? 'good' : (score >= 50 ? 'ok' : 'poor');
			var html = '<div class="q1-seo-score-container">';
			html += '<div class="q1-seo-score-bar"><div class="q1-seo-score-fill q1-seo-score-' + level + '" style="width:' + score + '%;"></div></div>';
			html += '<div class="q1-seo-score-badge q1-seo-score-' + level + '">' + score + '/100</div>';
			html += '</div>';
			return html;
		},

		renderSummary: function(recommendations) {
			var counts = { critical: 0, warning: 0, info: 0, success: 0 };
			(recommendations || []).forEach(function(r) {
				if (counts[r.severity] !== undefined) {
					counts[r.severity]++;
				}
			});

			var html = '<div class="q1-seo-summary-bar">';
			var s = q1SeoAudit.strings;
			var items = [
				{ key: 'critical', label: s.severityCritical || 'Critici', icon: '\u2717' },
				{ key: 'warning', label: s.severityWarning || 'Avvisi', icon: '!' },
				{ key: 'info', label: s.severityInfo || 'Info', icon: 'i' },
				{ key: 'success', label: s.severityOk || 'OK', icon: '\u2713' }
			];
			items.forEach(function(item) {
				if (counts[item.key] > 0) {
					html += '<span class="q1-summary-item q1-severity-' + item.key + '">';
					html += '<span class="q1-summary-icon">' + item.icon + '</span> ';
					html += counts[item.key] + ' ' + item.label + '</span>';
				}
			});
			html += '</div>';
			return html;
		},

		getCategoryIcon: function(category) {
			var icons = {
				'keyword': '\uD83D\uDD11',
				'meta': '\uD83C\uDFF7\uFE0F',
				'struttura': '\uD83D\uDCD0',
				'immagini': '\uD83D\uDDBC\uFE0F',
				'link': '\uD83D\uDD17',
				'tecnico': '\u2699\uFE0F'
			};
			return icons[category] || '\uD83D\uDCCB';
		},

		renderRecommendationsFull: function(recommendations) {
			var self = this;
			var order = { critical: 0, warning: 1, info: 2, success: 3 };
			var sorted = (recommendations || []).slice().sort(function(a, b) {
				return (order[a.severity] || 4) - (order[b.severity] || 4);
			});

			var html = '<div class="q1-seo-recommendations">';
			sorted.forEach(function(rec) {
				html += '<div class="q1-seo-rec-card q1-seo-rec-' + rec.severity + '">';
				html += '<div class="q1-seo-rec-header">';
				html += '<span class="q1-seo-rec-icon">' + self.getCategoryIcon(rec.category) + '</span>';
				html += '<span class="q1-seo-rec-title">' + self.escapeHtml(rec.title) + '</span>';
				html += '<span class="q1-seo-rec-severity q1-severity-' + rec.severity + '">' + rec.severity.toUpperCase() + '</span>';
				html += '</div>';
				html += '<div class="q1-seo-rec-body">';
				html += '<p>' + self.escapeHtml(rec.message) + '</p>';
				if (rec.suggestion) {
					html += '<div class="q1-seo-rec-suggestion"><strong>' + (q1SeoAudit.strings.suggestion || 'Suggerimento:') + '</strong> ' + self.escapeHtml(rec.suggestion) + '</div>';
				}
				html += '</div></div>';
			});
			html += '</div>';
			return html;
		},

		renderRecommendationsCompact: function(recommendations) {
			var self = this;
			var important = (recommendations || []).filter(function(r) {
				return r.severity === 'critical' || r.severity === 'warning';
			});

			var html = '<div class="q1-seo-recommendations-compact">';
			if (important.length === 0) {
				html += '<p class="q1-severity-success">' + (q1SeoAudit.strings.noIssues || 'Nessun problema critico rilevato.') + '</p>';
			} else {
				important.slice(0, 5).forEach(function(rec) {
					html += '<div class="q1-seo-rec-mini q1-seo-rec-' + rec.severity + '">';
					html += '<span class="q1-seo-rec-icon">' + self.getCategoryIcon(rec.category) + '</span> ';
					html += '<span>' + self.escapeHtml(rec.title) + '</span></div>';
				});
				if (important.length > 5) {
					html += '<p class="description">' + (q1SeoAudit.strings.moreWarnings || '...e altri %d avvisi').replace('%d', important.length - 5) + '</p>';
				}
			}
			html += '</div>';
			return html;
		},

		escapeHtml: function(text) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(text || ''));
			return div.innerHTML;
		},

		initRecentAuditsAccordion: function() {
			var self = this;
			$('.q1-seo-recent-audits').on('click', '.q1-seo-detail-toggle', function() {
				var $btn = $(this);
				var $row = $btn.closest('tr');
				var $next = $row.next('.q1-seo-detail-row');
				var s = q1SeoAudit.strings;

				if ($next.length) {
					$next.find('.q1-seo-detail-inner').slideUp(200, function() {
						$next.remove();
					});
					$btn.text(s.viewDetails || 'Vedi dettagli');
					return;
				}

				self.closeAllDetailRows();

				var auditData = $row.data('audit');
				if (!auditData) {
					return;
				}

				var $detailRow = self.createDetailRow(auditData);
				$row.after($detailRow);
				$detailRow.find('.q1-seo-detail-inner').hide().slideDown(300);
				$btn.text(s.hideDetails || 'Nascondi dettagli');
			});
		},

		closeAllDetailRows: function() {
			var s = q1SeoAudit.strings;
			$('.q1-seo-detail-row').each(function() {
				$(this).find('.q1-seo-detail-inner').slideUp(200, function() {
					$(this).closest('.q1-seo-detail-row').remove();
				});
			});
			$('.q1-seo-detail-toggle').text(s.viewDetails || 'Vedi dettagli');
		},

		createDetailRow: function(auditData) {
			var recommendations = auditData.recommendations || [];
			var html = '<tr class="q1-seo-detail-row"><td colspan="5"><div class="q1-seo-detail-inner">';
			html += this.renderSummary(recommendations);
			html += this.renderRecommendationsFull(recommendations);
			html += '</div></td></tr>';
			return $(html);
		}
	};

	$(document).ready(function() {
		if (typeof q1SeoAudit !== 'undefined') {
			Q1SeoAudit.init();
		}
	});

})(jQuery);
