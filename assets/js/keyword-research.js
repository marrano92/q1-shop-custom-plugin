(function($) {
	'use strict';

	var Q1KeywordResearch = {

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('#q1-keyword-search-btn').on('click', this.doSearch.bind(this));
			$('#q1-keyword-seed').on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					this.doSearch();
				}
			}.bind(this));
		},

		doSearch: function() {
			var keyword = $('#q1-keyword-seed').val().trim();

			if (!keyword) {
				this.showError(q1SeoKeyword.strings.emptyKeyword || 'Inserisci una parola chiave.');
				return;
			}

			this.setLoading(true);

			$.ajax({
				url: q1SeoKeyword.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_keyword_research',
					nonce: q1SeoKeyword.nonce,
					keyword_seed: keyword,
					use_auto_context: $('#q1-auto-context').is(':checked') ? '1' : '0'
				},
				success: function(response) {
					this.setLoading(false);
					if (response.success) {
						this.renderResults(response.data);
					} else {
						this.showError(response.data.message || 'Errore durante la ricerca.');
					}
				}.bind(this),
				error: function(xhr, status, error) {
					this.setLoading(false);
					this.showError('Errore di connessione: ' + error);
				}.bind(this),
				timeout: 60000
			});
		},

		setLoading: function(loading) {
			var $btn = $('#q1-keyword-search-btn');
			var $results = $('#q1-keyword-results');
			var $loading = $('#q1-keyword-loading');
			var $error = $('#q1-keyword-error');

			if (loading) {
				$btn.prop('disabled', true).text(q1SeoKeyword.strings.searching || 'Ricerca in corso...');
				$results.show();
				$loading.show();
				$error.hide();
				$('#q1-keyword-data').empty();
			} else {
				$btn.prop('disabled', false).text(q1SeoKeyword.strings.search || 'Cerca Keywords');
				$loading.hide();
			}
		},

		showError: function(message) {
			var $results = $('#q1-keyword-results');
			var $error = $('#q1-keyword-error');
			$results.show();
			$error.show().find('p').text(message);
		},

		renderResults: function(data) {
			var $container = $('#q1-keyword-data');
			$container.empty();
			this.currentData = data;
			this.currentKeywords = data.keywords.slice();

			if (!data.keywords || data.keywords.length === 0) {
				$container.html('<p>' + (q1SeoKeyword.strings.noResults || 'Nessun risultato trovato.') + '</p>');
				return;
			}

			this.renderMeta(data.meta);
			this.renderFilters();
			this.renderTable(this.currentKeywords);
			this.renderActions();
		},

		renderMeta: function(meta) {
			var html = '<div class="q1-seo-meta">';
			html += '<span>' + parseInt(meta.total_results, 10) + ' keyword trovate per "<strong>' + this.escapeHtml(meta.seed) + '</strong>"</span>';
			html += '<span>' + this.escapeHtml(new Date(meta.timestamp).toLocaleString('it-IT')) + '</span>';
			html += '</div>';
			$('#q1-keyword-data').append(html);
		},

		renderFilters: function() {
			var html = '<div class="q1-seo-filters">';
			html += '<label>Intento: <select id="q1-filter-intent">';
			html += '<option value="">Tutti</option>';
			html += '<option value="informazionale">Informazionale</option>';
			html += '<option value="transazionale">Transazionale</option>';
			html += '<option value="navigazionale">Navigazionale</option>';
			html += '</select></label>';
			html += '<label>Volume min: <input type="number" id="q1-filter-volume" value="0" min="0" step="100" class="small-text" /></label>';
			html += '</div>';
			$('#q1-keyword-data').append(html);

			$('#q1-filter-intent, #q1-filter-volume').on('change input', this.applyFilters.bind(this));
		},

		applyFilters: function() {
			var intent = $('#q1-filter-intent').val();
			var minVolume = parseInt($('#q1-filter-volume').val()) || 0;

			var filtered = this.currentData.keywords.filter(function(kw) {
				if (intent && kw.intent !== intent) return false;
				if (kw.volume < minVolume) return false;
				return true;
			});

			this.currentKeywords = filtered;
			$('.q1-seo-actions').remove();
			$('.q1-seo-table-wrapper').remove();
			this.renderTable(filtered);
			this.renderActions();
		},

		renderTable: function(keywords) {
			var html = '<div class="q1-seo-table-wrapper">';
			html += '<table class="q1-seo-table">';
			html += '<thead><tr>';
			html += '<th class="q1-col-check"><input type="checkbox" id="q1-select-all" /></th>';
			html += '<th data-sort="term">Keyword <span class="sort-indicator">&#x25B4;&#x25BE;</span></th>';
			html += '<th data-sort="volume">Volume <span class="sort-indicator">&#x25B4;&#x25BE;</span></th>';
			html += '<th>Trend 12m</th>';
			html += '<th data-sort="competition_index">Concorrenza <span class="sort-indicator">&#x25B4;&#x25BE;</span></th>';
			html += '<th data-sort="cpc">CPC <span class="sort-indicator">&#x25B4;&#x25BE;</span></th>';
			html += '<th data-sort="intent">Intento <span class="sort-indicator">&#x25B4;&#x25BE;</span></th>';
			html += '<th>Nota AI</th>';
			html += '</tr></thead>';
			html += '<tbody>';

			for (var i = 0; i < keywords.length; i++) {
				html += this.renderRow(keywords[i]);
			}

			html += '</tbody></table></div>';

			$('#q1-keyword-data').append(html);

			$('.q1-seo-table th[data-sort]').on('click', this.handleSort.bind(this));
			$('#q1-select-all').on('change', function() {
				$('.q1-kw-checkbox').prop('checked', this.checked);
			});
		},

		renderRow: function(kw) {
			var html = '<tr>';
			html += '<td><input type="checkbox" class="q1-kw-checkbox" value="' + this.escapeHtml(kw.term) + '" /></td>';
			html += '<td><strong>' + this.escapeHtml(kw.term) + '</strong></td>';
			html += '<td>' + this.formatNumber(kw.volume) + '</td>';
			html += '<td>' + this.renderSparkline(kw.trend_12m) + '</td>';
			html += '<td><span class="q1-competition--' + this.escapeHtml(kw.competition) + '">' + this.escapeHtml(kw.competition) + ' (' + parseInt(kw.competition_index, 10) + ')</span></td>';
			html += '<td>' + (kw.cpc ? '&euro;' + parseFloat(kw.cpc).toFixed(2) : '-') + '</td>';
			html += '<td><span class="q1-intent-badge q1-intent-badge--' + this.escapeHtml(kw.intent) + '">' + this.escapeHtml(kw.intent) + '</span></td>';
			html += '<td class="q1-ai-comment">' + this.escapeHtml(kw.ai_comment || '') + '</td>';
			html += '</tr>';
			return html;
		},

		renderSparkline: function(data) {
			if (!data || data.length === 0) return '-';

			var max = Math.max.apply(null, data);
			if (max === 0) return '-';

			var html = '<span class="q1-sparkline">';
			for (var i = 0; i < data.length; i++) {
				var height = Math.round((data[i] / max) * 18) + 2;
				var color = i === data.length - 1 ? '#2271b1' : '#c3c4c7';
				html += '<span style="display:inline-block;width:5px;height:' + height + 'px;background:' + color + ';margin:0 1px;vertical-align:bottom;"></span>';
			}
			html += '</span>';
			return html;
		},

		handleSort: function(e) {
			var $th = $(e.currentTarget);
			var field = $th.data('sort');
			var order = $th.hasClass('sorted-asc') ? 'desc' : 'asc';

			$('.q1-seo-table th').removeClass('sorted sorted-asc sorted-desc');
			$th.addClass('sorted sorted-' + order);

			this.currentKeywords.sort(function(a, b) {
				var valA = a[field];
				var valB = b[field];

				if (typeof valA === 'string') {
					valA = valA.toLowerCase();
					valB = (valB || '').toLowerCase();
				}

				if (order === 'asc') return valA > valB ? 1 : valA < valB ? -1 : 0;
				return valA < valB ? 1 : valA > valB ? -1 : 0;
			});

			var tbody = '';
			for (var i = 0; i < this.currentKeywords.length; i++) {
				tbody += this.renderRow(this.currentKeywords[i]);
			}
			$('.q1-seo-table tbody').html(tbody);
		},

		formatNumber: function(num) {
			if (num >= 1000) {
				return (num / 1000).toFixed(1).replace('.0', '') + 'K';
			}
			return num.toString();
		},

		renderActions: function() {
			var html = '<div class="q1-seo-actions">';
			html += '<button type="button" id="q1-export-csv" class="button">';
			html += (q1SeoKeyword.strings.exportCsv || 'Esporta CSV');
			html += '</button>';
			html += '<button type="button" id="q1-create-draft" class="button button-secondary" disabled>';
			html += (q1SeoKeyword.strings.createDraft || 'Crea bozza articolo');
			html += '</button>';
			html += '<span id="q1-selected-count" class="q1-seo-selected-count">' + (q1SeoKeyword.strings.selectedCount || '%d keyword selezionate').replace('%d', '0') + '</span>';
			html += '</div>';

			$('.q1-seo-table-wrapper').before(html);

			$('#q1-export-csv').on('click', this.exportCSV.bind(this));
			$('#q1-create-draft').on('click', this.createDraft.bind(this));
			$(document).on('change', '.q1-kw-checkbox, #q1-select-all', this.updateSelectedCount.bind(this));
		},

		updateSelectedCount: function() {
			var count = $('.q1-kw-checkbox:checked').length;
			$('#q1-selected-count').text((q1SeoKeyword.strings.selectedCount || '%d keyword selezionate').replace('%d', count));
			$('#q1-create-draft').prop('disabled', count === 0);
		},

		exportCSV: function() {
			var keywords = this.currentKeywords;
			if (!keywords || keywords.length === 0) return;

			var csv = 'Keyword,Volume,Concorrenza,Indice Concorrenza,CPC,Intento,Nota AI\n';

			for (var i = 0; i < keywords.length; i++) {
				var kw = keywords[i];
				csv += '"' + kw.term.replace(/"/g, '""') + '",';
				csv += kw.volume + ',';
				csv += kw.competition + ',';
				csv += kw.competition_index + ',';
				csv += (kw.cpc || 0) + ',';
				csv += kw.intent + ',';
				csv += '"' + (kw.ai_comment || '').replace(/"/g, '""') + '"\n';
			}

			var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = 'keyword-research-' + new Date().toISOString().slice(0, 10) + '.csv';
			link.click();
			URL.revokeObjectURL(url);
		},

		createDraft: function() {
			var selectedKeywords = [];
			$('.q1-kw-checkbox:checked').each(function() {
				selectedKeywords.push($(this).val());
			});

			if (selectedKeywords.length === 0) return;

			var primaryKeyword = selectedKeywords[0];

			if (!confirm((q1SeoKeyword.strings.confirmDraft || 'Creare una bozza articolo per "%s"?').replace('%s', primaryKeyword))) {
				return;
			}

			$.ajax({
				url: q1SeoKeyword.ajaxUrl,
				type: 'POST',
				data: {
					action: 'q1_seo_create_keyword_draft',
					nonce: q1SeoKeyword.nonce,
					keyword: primaryKeyword,
					all_keywords: selectedKeywords.join(',')
				},
				success: function(response) {
					if (response.success && response.data.edit_url) {
						window.location.href = response.data.edit_url;
					} else {
						alert(response.data.message || 'Errore nella creazione della bozza.');
					}
				},
				error: function() {
					alert(q1SeoKeyword.strings.connectionError || 'Errore di connessione.');
				}
			});
		},

		escapeHtml: function(text) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		}
	};

	$(document).ready(function() {
		Q1KeywordResearch.init();
	});

})(jQuery);
